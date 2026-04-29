<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

class TransactionSplitRequest extends FormRequest
{
    /**
     * Reject splits on transfer transactions — splitting an internal
     * transfer between own accounts has no spending semantics.
     */
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction
            && $transaction->type !== TransactionType::Transfer;
    }

    public function rules(): array
    {
        return [
            'splits' => ['required', 'array', 'min:2'],
            'splits.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'splits.*.amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $transaction = $this->route('transaction');
            if (! $transaction instanceof Transaction) {
                return;
            }

            $splits = $this->input('splits', []);

            // The base rules ('splits' => required|array|min:2, plus per-row
            // rules) may already have failed; Laravel still runs after-hooks
            // anyway. Bail out before treating $splits as an array of arrays
            // so a malformed payload turns into a clean 422 instead of a 500.
            if (! is_array($splits)) {
                return;
            }
            foreach ($splits as $split) {
                if (! is_array($split)) {
                    return;
                }
            }

            // Sum of split amounts must equal the transaction's absolute amount.
            $sum = array_sum(array_map(
                fn ($s) => (float) ($s['amount'] ?? 0),
                $splits
            ));
            $expected = abs((float) $transaction->amount);

            if (round($sum, 2) !== round($expected, 2)) {
                $validator->errors()->add(
                    'splits',
                    "Totaal van splits (€ {$sum}) moet gelijk zijn aan transactiebedrag (€ {$expected})."
                );
            }

            // All split categories must match the transaction's type
            // (income → income categories, expense → expense categories).
            $expectedType = $transaction->type->value;
            $categoryIds = array_filter(array_map(
                fn ($s) => $s['category_id'] ?? null,
                $splits
            ));
            $categories = Category::whereIn('id', $categoryIds)->get()->keyBy('id');

            foreach ($splits as $index => $split) {
                $catId = $split['category_id'] ?? null;
                $category = $categories[$catId] ?? null;
                if (! $category) {
                    continue; // exists rule already flagged this
                }

                if ($category->is_system) {
                    $validator->errors()->add(
                        "splits.{$index}.category_id",
                        'Systeemcategorieën kunnen niet aan een split worden toegewezen.'
                    );
                    continue;
                }

                if ((string) $category->type !== $expectedType) {
                    $validator->errors()->add(
                        "splits.{$index}.category_id",
                        'Categorie type komt niet overeen met transactie type.'
                    );
                }
            }
        });
    }
}
