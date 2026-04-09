<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Services\CategoryRuleService;
use App\Services\CsvImportService;
use App\Services\RabobankCsvParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CsvImportController extends Controller
{
    public function __construct(
        private RabobankCsvParser $parser,
        private CsvImportService $service,
        private CategoryRuleService $ruleService,
    ) {}

    /**
     * Step 1: show the upload form.
     */
    public function create(): Response
    {
        $this->cleanupStaleStashes();

        return Inertia::render('CsvImports/Create');
    }

    /**
     * Opportunistic cleanup of abandoned uploads. Anything in csv-imports/
     * older than 24h is removed. Runs on every visit to the upload form so
     * we don't need a scheduler. Phase 9b will replace this with a proper
     * scheduled command.
     */
    private function cleanupStaleStashes(): void
    {
        $cutoff = now()->subDay()->getTimestamp();

        foreach (Storage::files('csv-imports') as $path) {
            if (Storage::lastModified($path) < $cutoff) {
                Storage::delete($path);
            }
        }
    }

    /**
     * Step 2 (POST): stash the uploaded file under a random token and
     * redirect to the GET preview route. Following the Post-Redirect-Get
     * pattern so refresh/back/bookmark work on the preview page.
     */
    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('csv');
        $token = Str::random(40);
        $stashPath = "csv-imports/{$token}.csv";
        $metaPath = "csv-imports/{$token}.json";

        Storage::put($stashPath, file_get_contents($file->getRealPath()));
        Storage::put($metaPath, json_encode([
            'original_filename' => $file->getClientOriginalName(),
            'expires_at' => now()->addDay()->toIso8601String(),
        ]));

        return Redirect::route('csv-imports.preview', ['token' => $token]);
    }

    /**
     * Step 3 (GET): parse the stashed file, detect accounts, render preview.
     */
    public function preview(string $token): Response|RedirectResponse
    {
        $stashPath = "csv-imports/{$token}.csv";
        $metaPath = "csv-imports/{$token}.json";

        if (! Storage::exists($stashPath) || ! Storage::exists($metaPath)) {
            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => 'Upload is verlopen. Upload het bestand opnieuw.']);
        }

        $meta = json_decode(Storage::get($metaPath), true);

        try {
            $grouped = $this->parser->parse(Storage::path($stashPath));
        } catch (\RuntimeException $e) {
            Storage::delete([$stashPath, $metaPath]);

            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => $e->getMessage()]);
        }

        $sections = [];
        $missing = [];

        foreach ($grouped as $iban => $rows) {
            $account = $this->service->detectAccount($iban);

            if ($account === null) {
                $missing[] = $iban;
                continue;
            }

            $preview = $this->service->buildPreview($rows, $account);
            $previewRows = $this->ruleService->applyToRows($preview['rows']);
            $sections[] = [
                'account' => ['id' => $account->id, 'name' => $account->name],
                'rows' => $previewRows,
                'summary' => $preview['summary'],
            ];
        }

        // When the CSV contains IBANs that don't match any existing account,
        // show an inline form so the user can create the missing accounts and
        // continue to the preview. NOTE: this intermediate step may be removed
        // in a future phase if the workflow proves unnecessary — see
        // implementation plan Phase 4a notes.
        if (! empty($missing)) {
            return Inertia::render('CsvImports/MissingAccounts', [
                'token' => $token,
                'missingIbans' => $missing,
                'originalFilename' => $meta['original_filename'] ?? 'import.csv',
            ]);
        }

        $categories = Category::select('id', 'name', 'type', 'color')
            ->where('is_system', false)
            ->orderBy('name')
            ->get();

        $transferCategory = Category::where('name', 'Overboeking')
            ->where('is_system', true)
            ->first();

        return Inertia::render('CsvImports/Preview', [
            'token' => $token,
            'originalFilename' => $meta['original_filename'] ?? 'import.csv',
            'sections' => $sections,
            'categories' => $categories,
            'transferCategoryId' => $transferCategory?->id,
        ]);
    }

    /**
     * Step 3b (POST): create accounts for IBANs found in the CSV that didn't
     * match any existing account, then redirect back to the preview.
     *
     * NOTE: This method may be removed in a future phase if the workflow
     * proves unnecessary — see implementation plan Phase 4a notes.
     */
    public function createAccounts(Request $request, string $token): RedirectResponse
    {
        $stashPath = "csv-imports/{$token}.csv";
        $metaPath = "csv-imports/{$token}.json";

        if (! Storage::exists($stashPath) || ! Storage::exists($metaPath)) {
            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => 'Upload is verlopen. Upload het bestand opnieuw.']);
        }

        $validated = $request->validate([
            'accounts' => ['required', 'array', 'min:1'],
            'accounts.*.iban' => ['required', 'string', 'max:34'],
            'accounts.*.name' => ['required', 'string', 'max:255'],
            'accounts.*.type' => ['required', 'string', 'in:checking,savings,cash'],
            'accounts.*.starting_balance' => ['required', 'numeric', 'decimal:0,2', 'min:-9999999.99', 'max:9999999.99'],
        ]);

        foreach ($validated['accounts'] as $data) {
            Account::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'starting_balance' => $data['starting_balance'],
                'iban' => strtoupper(preg_replace('/\s+/', '', $data['iban']) ?? ''),
            ]);
        }

        return Redirect::route('csv-imports.preview', ['token' => $token]);
    }

    /**
     * Cancel an in-progress import by deleting the stashed file.
     */
    public function cancel(string $token): RedirectResponse
    {
        Storage::delete([
            "csv-imports/{$token}.csv",
            "csv-imports/{$token}.json",
        ]);

        return Redirect::route('csv-imports.create');
    }

    /**
     * Step 4 (POST): commit the import. Re-parses the stashed file so we
     * trust the server-side data, not whatever the frontend sends back.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:40'],
            'categories' => ['required', 'array'],
            'categories.*' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $token = $request->string('token')->toString();
        $categoryMap = $request->input('categories');
        $stashPath = "csv-imports/{$token}.csv";
        $metaPath = "csv-imports/{$token}.json";

        if (! Storage::exists($stashPath) || ! Storage::exists($metaPath)) {
            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => 'Upload is verlopen. Upload het bestand opnieuw.']);
        }

        $meta = json_decode(Storage::get($metaPath), true);
        $originalFilename = $meta['original_filename'] ?? 'import.csv';

        $totalNew = 0;
        $totalSkipped = 0;
        $firstAccountId = null;

        try {
            $grouped = $this->parser->parse(Storage::path($stashPath));

            DB::transaction(function () use ($grouped, $originalFilename, $categoryMap, &$totalNew, &$totalSkipped, &$firstAccountId) {
                foreach ($grouped as $iban => $rows) {
                    $account = $this->service->detectAccount($iban);
                    if ($account === null) {
                        throw new \RuntimeException("Rekening voor IBAN {$iban} niet meer gevonden.");
                    }

                    $preview = $this->service->buildPreview($rows, $account);
                    $import = $this->service->commit(
                        $preview['rows'],
                        $account,
                        $originalFilename,
                        $categoryMap,
                    );

                    $totalNew += $import->imported_count;
                    $totalSkipped += $import->skipped_count;
                    $firstAccountId ??= $account->id;
                }
            });
        } catch (\RuntimeException $e) {
            Storage::delete([$stashPath, $metaPath]);

            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => $e->getMessage()]);
        }

        Storage::delete([$stashPath, $metaPath]);

        if ($firstAccountId === null) {
            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => 'Geen transacties geïmporteerd.']);
        }

        return Redirect::route('accounts.show', $firstAccountId)->with(
            'success',
            "Import voltooid: {$totalNew} nieuw, {$totalSkipped} overgeslagen."
        );
    }
}
