<?php

namespace App\Http\Controllers;

use App\Services\CsvImportService;
use App\Services\RabobankCsvParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {}

    /**
     * Step 1: show the upload form.
     */
    public function create(): Response
    {
        return Inertia::render('CsvImports/Create');
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
            $sections[] = [
                'account' => ['id' => $account->id, 'name' => $account->name],
                'rows' => $preview['rows'],
                'summary' => $preview['summary'],
            ];
        }

        if (! empty($missing)) {
            Storage::delete([$stashPath, $metaPath]);

            return Redirect::route('csv-imports.create')->withErrors([
                'csv' => 'Geen rekening gevonden voor IBAN: '.implode(', ', $missing).'. Maak deze eerst aan.',
            ]);
        }

        return Inertia::render('CsvImports/Preview', [
            'token' => $token,
            'originalFilename' => $meta['original_filename'] ?? 'import.csv',
            'sections' => $sections,
        ]);
    }

    /**
     * Step 4 (POST): commit the import. Re-parses the stashed file so we
     * trust the server-side data, not whatever the frontend sends back.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:40'],
        ]);

        $token = $request->string('token')->toString();
        $stashPath = "csv-imports/{$token}.csv";
        $metaPath = "csv-imports/{$token}.json";

        if (! Storage::exists($stashPath) || ! Storage::exists($metaPath)) {
            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => 'Upload is verlopen. Upload het bestand opnieuw.']);
        }

        $meta = json_decode(Storage::get($metaPath), true);
        $originalFilename = $meta['original_filename'] ?? 'import.csv';

        try {
            $grouped = $this->parser->parse(Storage::path($stashPath));
            $totalNew = 0;
            $totalSkipped = 0;
            $firstAccountId = null;

            foreach ($grouped as $iban => $rows) {
                $account = $this->service->detectAccount($iban);
                if ($account === null) {
                    return Redirect::route('csv-imports.create')
                        ->withErrors(['csv' => "Rekening voor IBAN {$iban} niet meer gevonden."]);
                }

                $preview = $this->service->buildPreview($rows, $account);
                $import = $this->service->commit(
                    $preview['rows'],
                    $account,
                    $originalFilename,
                );

                $totalNew += $import->imported_count;
                $totalSkipped += $import->skipped_count;
                $firstAccountId ??= $account->id;
            }
        } finally {
            Storage::delete([$stashPath, $metaPath]);
        }

        return Redirect::route('accounts.show', $firstAccountId)->with(
            'success',
            "Import voltooid: {$totalNew} nieuw, {$totalSkipped} overgeslagen."
        );
    }
}
