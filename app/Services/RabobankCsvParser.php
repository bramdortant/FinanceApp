<?php

namespace App\Services;

use RuntimeException;

/**
 * Parser for Rabobank CSV exports.
 *
 * Rabobank uses a specific column layout (see expected headers below) with:
 *   - Comma-separated, double-quoted fields
 *   - Dutch number format for amounts: "+1234,56" / "-12,50" (comma decimal)
 *   - Dates in YYYY-MM-DD
 *   - Three description columns (Omschrijving-1/2/3) that we concatenate
 */
class RabobankCsvParser
{
    /**
     * Headers we require to be present (order-independent). If any are
     * missing, we abort early — silently parsing the wrong format would
     * produce nonsense transactions.
     */
    private const REQUIRED_HEADERS = [
        'IBAN/BBAN',
        'Munt',
        'Datum',
        'Bedrag',
        'Saldo na trn',
        'Tegenrekening IBAN/BBAN',
        'Naam tegenpartij',
        'Code',
        'Omschrijving-1',
        'Omschrijving-2',
        'Omschrijving-3',
    ];

    /**
     * Parse the file at $path into normalized rows, grouped by owner IBAN.
     *
     * One Rabobank CSV may contain rows for multiple accounts (e.g. when
     * downloading via "Download transacties" with multiple accounts
     * selected). The first column of each row is the account that owns
     * that row, so we group rows by that column.
     *
     * @return array<string, array<int, array<string, mixed>>> Map of owner IBAN → rows
     */
    public function parse(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("CSV file is not readable: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open CSV file: {$path}");
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new RuntimeException('CSV file is empty.');
            }

            // Strip a possible UTF-8 BOM from the first header so the
            // header lookup below still matches "IBAN/BBAN" exactly.
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0] ?? '') ?? '';

            $missing = array_diff(self::REQUIRED_HEADERS, $headers);
            if (! empty($missing)) {
                throw new RuntimeException(
                    'CSV mist verplichte kolommen: '.implode(', ', $missing)
                );
            }

            $index = array_flip($headers);
            $grouped = [];
            $lineNumber = 1;

            while (($raw = fgetcsv($handle)) !== false) {
                $lineNumber++;

                // Skip completely blank lines (some exports have a trailing newline).
                if ($raw === [null] || (count($raw) === 1 && trim((string) $raw[0]) === '')) {
                    continue;
                }

                $get = fn (string $col) => isset($index[$col], $raw[$index[$col]])
                    ? trim((string) $raw[$index[$col]])
                    : '';

                $iban = $this->normalizeIban($get('IBAN/BBAN'));
                if ($iban === '') {
                    throw new RuntimeException("Lege IBAN op regel {$lineNumber}.");
                }

                $currency = $get('Munt');
                if ($currency !== '' && $currency !== 'EUR') {
                    throw new RuntimeException(
                        "Alleen EUR wordt ondersteund (regel {$lineNumber} is {$currency})."
                    );
                }

                $bedrag = $get('Bedrag');
                if ($bedrag === '') {
                    throw new RuntimeException("Lege Bedrag op regel {$lineNumber}.");
                }

                $saldo = $get('Saldo na trn');

                $desc = $this->joinDescriptions(
                    $get('Omschrijving-1'),
                    $get('Omschrijving-2'),
                    $get('Omschrijving-3'),
                );

                $grouped[$iban][] = [
                    'date' => $get('Datum'),
                    'amount' => $this->normalizeAmount($bedrag),
                    'balance_after' => $saldo === '' ? null : $this->normalizeAmount($saldo),
                    'counterparty_iban' => $get('Tegenrekening IBAN/BBAN') ?: null,
                    'counterparty_name' => $get('Naam tegenpartij') ?: null,
                    'transaction_code' => $get('Code') ?: null,
                    'description' => $desc,
                    'original_description' => $desc,
                ];
            }

            if (empty($grouped)) {
                throw new RuntimeException('Geen rijen gevonden in CSV.');
            }

            return $grouped;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Convert "+1234,56" / "-12,50" / "1.234,56" → "1234.56" / "-12.50".
     * Returns a string so downstream BC math stays exact.
     */
    private function normalizeAmount(string $value): string
    {
        if ($value === '') {
            return '0.00';
        }

        // Strip thousand separators (dots), then swap decimal comma for dot.
        $clean = str_replace('.', '', $value);
        $clean = str_replace(',', '.', $clean);

        // bcadd with scale 2 normalizes to two decimals and validates the format.
        try {
            return bcadd($clean, '0', 2);
        } catch (\ValueError|\TypeError $e) {
            throw new RuntimeException("Ongeldig bedrag in CSV: '{$value}'.");
        }
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    private function joinDescriptions(string ...$parts): string
    {
        $filtered = array_filter(array_map('trim', $parts), fn ($p) => $p !== '');

        return implode(' ', $filtered);
    }
}
