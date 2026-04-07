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

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            // For transfers: the destination account.
            'transfer_to_account_id' => [
                'nullable',
                'required_if:type,transfer',
                'exists:accounts,id',
                'different:account_id_for_validation',
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
            if ($category && $category->type !== $type) {
                $validator->errors()->add('category_id', 'Categorie type komt niet overeen met transactie type.');
            }
        });
    }
}
