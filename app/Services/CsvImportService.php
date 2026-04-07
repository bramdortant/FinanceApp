<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CsvImport;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates a CSV import: account detection, duplicate detection,
 * transfer detection, and committing the import.
 *
 * The service is split from the parser so we can plug in other bank
 * formats later (ING, ABN) without rewriting this logic.
 */
class CsvImportService
{
    /**
     * Find the account whose IBAN matches the given value.
     *
     * IBANs are stored encrypted, so we can't WHERE on them in SQL.
     * We load every account and compare in PHP — this is fine because
     * a personal-finance app only has a handful of accounts.
     */
    public function detectAccount(string $iban): ?Account
    {
        $needle = $this->normalizeIban($iban);

        return Account::all()->first(
            fn (Account $a) => $a->iban && $this->normalizeIban($a->iban) === $needle
        );
    }

    /**
     * Annotate parsed rows with status (new / duplicate / transfer) and a
     * preview-friendly shape. Does NOT touch the database.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function buildPreview(array $rows, Account $account): array
    {
        // Pre-load own accounts once so transfer detection doesn't query per row.
        $ownAccounts = Account::all()->filter(fn (Account $a) => (bool) $a->iban);

        // Pre-fetch existing hashes for this account so we can flag duplicates
        // without N queries. One SELECT, in-memory lookup.
        $existingHashes = Transaction::where('account_id', $account->id)
            ->whereNotNull('csv_import_hash')
            ->pluck('csv_import_hash')
            ->flip();

        $summary = ['new' => 0, 'duplicate' => 0, 'transfer' => 0, 'mirror' => 0];
        $annotated = [];

        foreach ($rows as $row) {
            $hash = $this->hashRow($account->id, $row);

            $transferTarget = $this->detectTransferTarget($row, $ownAccounts, $account);

            // For an internal transfer between two own accounts, both sides
            // appear in the CSV (one negative, one positive). We only keep
            // the negative "source" side and skip the positive "mirror" side
            // — Phase 3's storage model uses a single signed row + transfer_to.
            $isMirror = $transferTarget !== null
                && bccomp($row['amount'], '0', 2) > 0;

            if (isset($existingHashes[$hash])) {
                $status = 'duplicate';
                $summary['duplicate']++;
            } elseif ($isMirror) {
                $status = 'transfer_mirror';
                $summary['mirror']++;
            } elseif ($transferTarget !== null) {
                $status = 'transfer';
                $summary['transfer']++;
                $summary['new']++;
            } else {
                $status = 'new';
                $summary['new']++;
            }

            $annotated[] = array_merge($row, [
                'hash' => $hash,
                'status' => $status,
                'transfer_to_account_id' => $transferTarget?->id,
                'transfer_to_account_name' => $transferTarget?->name,
                'type' => $this->resolveType($row, $transferTarget),
            ]);
        }

        return ['rows' => $annotated, 'summary' => $summary];
    }

    /**
     * Persist the import. Wraps everything in a single DB transaction so
     * a partial failure leaves the database clean.
     *
     * Returns the CsvImport record so the caller can show a result page.
     */
    public function commit(array $previewRows, Account $account, string $filename): CsvImport
    {
        return DB::transaction(function () use ($previewRows, $account, $filename) {
            $toInsert = array_filter(
                $previewRows,
                fn (array $r) => $r['status'] !== 'duplicate' && $r['status'] !== 'transfer_mirror'
            );

            $import = CsvImport::create([
                'filename' => $filename,
                'account_id' => $account->id,
                'row_count' => count($previewRows),
                'imported_count' => count($toInsert),
                'skipped_count' => count($previewRows) - count($toInsert),
            ]);

            foreach ($toInsert as $row) {
                Transaction::create([
                    'account_id' => $account->id,
                    'date' => $row['date'],
                    'description' => $row['description'] ?: '—',
                    'amount' => $row['amount'],
                    'original_description' => $row['original_description'],
                    'category_id' => null,
                    'type' => $row['type'],
                    'transfer_to_account_id' => $row['transfer_to_account_id'],
                    'counterparty_name' => $row['counterparty_name'],
                    'counterparty_iban' => $row['counterparty_iban'],
                    'balance_after' => $row['balance_after'],
                    'transaction_code' => $row['transaction_code'],
                    'csv_import_hash' => $row['hash'],
                    'csv_import_id' => $import->id,
                ]);
            }

            return $import;
        });
    }

    /**
     * Stable per-row fingerprint for duplicate detection.
     * Uses fields the bank doesn't randomize between exports of the same period.
     */
    private function hashRow(int $accountId, array $row): string
    {
        return sha1(implode('|', [
            $accountId,
            $row['date'],
            $row['amount'],
            $row['original_description'],
            $row['counterparty_iban'] ?? '',
        ]));
    }

    private function detectTransferTarget(array $row, $ownAccounts, Account $current): ?Account
    {
        $iban = $row['counterparty_iban'] ?? null;
        if (! $iban) {
            return null;
        }

        $needle = $this->normalizeIban($iban);

        return $ownAccounts->first(function (Account $a) use ($needle, $current) {
            return $a->id !== $current->id
                && $this->normalizeIban($a->iban) === $needle;
        });
    }

    private function resolveType(array $row, ?Account $transferTarget): string
    {
        if ($transferTarget !== null) {
            return TransactionType::Transfer->value;
        }

        return bccomp($row['amount'], '0', 2) >= 0
            ? TransactionType::Income->value
            : TransactionType::Expense->value;
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }
}
