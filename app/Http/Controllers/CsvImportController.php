<?php

namespace App\Http\Controllers;

use App\Models\Account;
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
     * Step 2: parse the uploaded file, detect the account, build a preview.
     *
     * The file is stashed in private storage under a random token. The
     * preview page submits that token to `store()` for the actual commit,
     * so we don't need to keep the parsed rows in the session.
     */
    public function preview(Request $request): Response|RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $file = $request->file('csv');
        $token = Str::random(40);
        $stashPath = "csv-imports/{$token}.csv";
        Storage::put($stashPath, file_get_contents($file->getRealPath()));

        try {
            $parsed = $this->parser->parse(Storage::path($stashPath));
        } catch (\RuntimeException $e) {
            Storage::delete($stashPath);

            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => $e->getMessage()]);
        }

        $account = $this->service->detectAccount($parsed['owner_iban']);

        if ($account === null) {
            // No matching account: bail out and ask the user to create one.
            // We deliberately keep this simple in Phase 4a — the user picks
            // or creates the account before retrying the upload.
            Storage::delete($stashPath);

            return Redirect::route('csv-imports.create')->withErrors([
                'csv' => "Geen rekening gevonden met IBAN {$parsed['owner_iban']}. Maak eerst de rekening aan.",
            ]);
        }

        $preview = $this->service->buildPreview($parsed['rows'], $account);

        return Inertia::render('CsvImports/Preview', [
            'token' => $token,
            'originalFilename' => $file->getClientOriginalName(),
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
            ],
            'rows' => $preview['rows'],
            'summary' => $preview['summary'],
        ]);
    }

    /**
     * Step 3: commit the import. Re-parses the stashed file so we trust
     * the server-side data, not whatever the frontend sends back.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:40'],
            'original_filename' => ['required', 'string', 'max:255'],
        ]);

        $stashPath = "csv-imports/{$request->string('token')}.csv";

        if (! Storage::exists($stashPath)) {
            return Redirect::route('csv-imports.create')
                ->withErrors(['csv' => 'Upload is verlopen. Upload het bestand opnieuw.']);
        }

        try {
            $parsed = $this->parser->parse(Storage::path($stashPath));
            $account = $this->service->detectAccount($parsed['owner_iban']);

            if ($account === null) {
                return Redirect::route('csv-imports.create')
                    ->withErrors(['csv' => 'Rekening niet meer gevonden.']);
            }

            $preview = $this->service->buildPreview($parsed['rows'], $account);
            $import = $this->service->commit(
                $preview['rows'],
                $account,
                $request->string('original_filename')->toString(),
            );
        } finally {
            Storage::delete($stashPath);
        }

        return Redirect::route('accounts.show', $account->id)->with(
            'success',
            "Import voltooid: {$import->imported_count} nieuw, {$import->skipped_count} overgeslagen."
        );
    }
}
