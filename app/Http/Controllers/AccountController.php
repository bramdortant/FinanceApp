<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = Account::withSum('transactions', 'amount')
            ->get()
            ->map(function (Account $account) {
                $account->current_balance = bcadd(
                    $account->starting_balance,
                    $account->transactions_sum_amount ?? '0',
                    2
                );

                return $account;
            });

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Create');
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        Account::create($request->validated());

        return Redirect::route('accounts.index');
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

        return Redirect::route('accounts.index');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $account->delete();

        return Redirect::route('accounts.index');
    }
}
