<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\TransactionRequest;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class TransactionController extends Controller
{
    /**
     * Income rows are stored positive; expense and transfer source rows are
     * stored negative. Inbound transfers are derived (not duplicated) by
     * AccountController::balanceMap() looking at transfer_to_account_id.
     */
    private function signedAmount(string $amount, string $type): string
    {
        if ($type === TransactionType::Expense->value || $type === TransactionType::Transfer->value) {
            return bcmul($amount, '-1', 2);
        }

        return bcadd($amount, '0', 2);
    }

    public function store(TransactionRequest $request, Account $account): RedirectResponse
    {
        $data = $request->validated();
        $type = $data['type'];
        $amount = $this->signedAmount((string) $data['amount'], $type);

        $account->transactions()->create([
            'date' => $data['date'],
            'description' => $data['description'] ?? '',
            'amount' => $amount,
            'type' => $type,
            'category_id' => $type === TransactionType::Transfer->value ? null : ($data['category_id'] ?? null),
            'transfer_to_account_id' => $type === TransactionType::Transfer->value
                ? $data['transfer_to_account_id']
                : null,
            'notes' => $data['notes'] ?? null,
        ]);

        return Redirect::route('accounts.show', $account)
            ->with('success', 'Transactie toegevoegd.');
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validated();
        $type = $data['type'];
        $amount = $this->signedAmount((string) $data['amount'], $type);

        $transaction->update([
            'date' => $data['date'],
            'description' => $data['description'] ?? '',
            'amount' => $amount,
            'type' => $type,
            'category_id' => $type === TransactionType::Transfer->value ? null : ($data['category_id'] ?? null),
            'transfer_to_account_id' => $type === TransactionType::Transfer->value
                ? $data['transfer_to_account_id']
                : null,
            'notes' => $data['notes'] ?? null,
        ]);

        return Redirect::route('accounts.show', $transaction->account_id)
            ->with('success', 'Transactie bijgewerkt.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $accountId = $transaction->account_id;
        $transaction->delete();

        return Redirect::route('accounts.show', $accountId)
            ->with('success', 'Transactie verwijderd.');
    }
}
