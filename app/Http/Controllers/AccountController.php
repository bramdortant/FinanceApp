<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = Account::all()->map(function (Account $account) {
            $account->current_balance = $this->calculateBalance($account);

            return $account;
        });

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function all(): Response
    {
        $totalBalance = Account::all()->reduce(
            fn ($carry, Account $account) => bcadd($carry, $this->calculateBalance($account), 2),
            '0'
        );

        $transactions = Transaction::query()
            ->with(['category:id,name,color', 'account:id,name', 'transferToAccount:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return Inertia::render('Accounts/All', [
            'totalBalance' => $totalBalance,
            'transactions' => $transactions,
        ]);
    }

    public function show(Account $account): Response
    {
        $account->current_balance = $this->calculateBalance($account);

        $transactions = Transaction::query()
            ->where(function ($query) use ($account) {
                $query->where('account_id', $account->id)
                    ->orWhere('transfer_to_account_id', $account->id);
            })
            ->with(['category:id,name,color', 'transferToAccount:id,name', 'account:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Transaction $transaction) use ($account) {
                // For inbound transfers, the source row's amount is negative on the
                // source account; from this account's perspective it should be positive.
                $transaction->display_amount = $transaction->transfer_to_account_id === $account->id
                    ? bcmul((string) $transaction->amount, '-1', 2)
                    : (string) $transaction->amount;

                return $transaction;
            });

        $accounts = Account::select('id', 'name')->get();
        $categories = Category::select('id', 'name', 'type', 'color')->orderBy('name')->get();

        return Inertia::render('Accounts/Show', [
            'account' => $account,
            'transactions' => $transactions,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    private function calculateBalance(Account $account): string
    {
        $outgoing = (string) ($account->transactions()->sum('amount') ?: '0');
        $incomingTransfers = (string) (Transaction::where('transfer_to_account_id', $account->id)->sum('amount') ?: '0');

        // Inbound transfers are negative on the source row, so subtract to add their absolute value.
        $balance = bcadd((string) $account->starting_balance, $outgoing, 2);
        $balance = bcsub($balance, $incomingTransfers, 2);

        return $balance;
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Create');
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        Account::create($request->validated());

        return Redirect::route('accounts.index')
            ->with('success', 'Rekening aangemaakt.');
    }

    public function edit(Account $account): Response
    {
        return Inertia::render('Accounts/Edit', [
            'account' => $account,
        ]);
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->validated());

        return Redirect::route('accounts.index')
            ->with('success', 'Rekening bijgewerkt.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->transactions()->exists()) {
            return Redirect::route('accounts.index')
                ->with('error', 'Kan een rekening met transacties niet verwijderen.');
        }

        $account->delete();

        return Redirect::route('accounts.index')
            ->with('success', 'Rekening verwijderd.');
    }
}
