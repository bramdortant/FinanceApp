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
    public function store(TransactionRequest $request, Account $account): RedirectResponse
    {
        $data = $request->validated();
        $type = $data['type'];

        // Income/expense rows are stored signed: expense = negative, income = positive.
        // Transfers are stored as a single source row (negative on the source account)
        // with transfer_to_account_id pointing at the destination. The destination
        // balance picks it up via the inbound-transfer query in AccountController.
        $amount = (string) $data['amount'];
        if ($type === TransactionType::Expense->value || $type === TransactionType::Transfer->value) {
            $amount = bcmul($amount, '-1', 2);
        }

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

        $amount = (string) $data['amount'];
        if ($type === TransactionType::Expense->value || $type === TransactionType::Transfer->value) {
            $amount = bcmul($amount, '-1', 2);
        }

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
