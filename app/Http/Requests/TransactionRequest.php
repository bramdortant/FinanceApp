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
        $account = $this->route('account');
        $transaction = $this->route('transaction');

        if ($account) {
            $this->merge(['source_account_id' => $account->id]);
        } elseif ($transaction) {
            $this->merge(['source_account_id' => $transaction->account_id]);
        }

        // Transfers get the system "Overboeking" category automatically —
        // the frontend doesn't need to send one.
        if ($this->input('type') === TransactionType::Transfer->value) {
            $transferCategory = Category::where('name', 'Overboeking')
                ->where('is_system', true)
                ->first();

            if ($transferCategory) {
                $this->merge(['category_id' => $transferCategory->id]);
            }
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
            'category_id' => ['required', 'exists:categories,id'],
            'source_account_id' => ['nullable', 'integer'],
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

            // Skip category-type validation for transfers (system category).
            if ($type === TransactionType::Transfer->value) {
                return;
            }

            $category = Category::find($this->input('category_id'));
            if ($category && $category->is_system) {
                $validator->errors()->add('category_id', 'Systeemcategorieën kunnen niet handmatig worden toegewezen.');

                return;
            }

            if ($category && (string) $category->type !== (string) $type) {
                $validator->errors()->add('category_id', 'Categorie type komt niet overeen met transactie type.');
            }
        });
    }
}
