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
            // Shape check only (country code + 2 check digits + alphanumeric body).
// Does not verify the IBAN checksum — a dedicated package would be needed for that.
'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/'],
        ];
    }
}
