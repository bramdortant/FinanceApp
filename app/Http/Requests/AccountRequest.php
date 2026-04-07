<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:checking,savings,cash'],
            'starting_balance' => ['required', 'numeric', 'decimal:0,2', 'min:-9999999.99', 'max:9999999.99'],
            'iban' => ['nullable', 'string', 'max:34'],
        ];
    }
}
