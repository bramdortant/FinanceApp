<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Inject the current account id (from the route) into the validated data
     * so the `different` rule below can compare against it. The frontend never
     * submits this field — it always comes from the URL.
     */
    protected function prepareForValidation(): void
    {
        // store: source comes from {account} route param
        // update: source comes from the existing transaction
        $account = $this->route('account');
        $transaction = $this->route('transaction');

        if ($account) {
            $this->merge(['source_account_id' => $account->id]);
        } elseif ($transaction) {
            $this->merge(['source_account_id' => $transaction->account_id]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'source_account_id' => ['nullable', 'integer'],
            // For transfers: the destination account. Must differ from source
            // to prevent transferring an account to itself.
            'transfer_to_account_id' => [
                'nullable',
                'required_if:type,transfer',
                'exists:accounts,id',
                'different:source_account_id',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');

            // Transfers must not have a category, income/expense should match the category type if a category is set.
            if ($type === TransactionType::Transfer->value) {
                if ($this->filled('category_id')) {
                    $validator->errors()->add('category_id', 'Overboekingen mogen geen categorie hebben.');
                }

                return;
            }

            if (! $this->filled('category_id')) {
                return;
            }

            $category = Category::find($this->input('category_id'));
            if ($category && (string) $category->type !== (string) $type) {
                $validator->errors()->add('category_id', 'Categorie type komt niet overeen met transactie type.');
            }
        });
    }
}
