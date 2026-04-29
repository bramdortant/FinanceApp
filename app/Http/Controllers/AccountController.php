<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = Account::all();
        $balances = $this->balanceMap($accounts);

        $accounts = $accounts->map(function (Account $account) use ($balances) {
            $account->current_balance = $balances[$account->id] ?? (string) $account->starting_balance;

            return $account;
        });

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function all(): Response
    {
        $accounts = Account::all();
        $balances = $this->balanceMap($accounts);
        $totalBalance = array_reduce($balances, fn ($carry, $bal) => bcadd($carry, $bal, 2), '0');

        $transactions = Transaction::query()
            ->with([
                'category:id,name,color',
                'account:id,name',
                'transferToAccount:id,name',
                'splits:id,transaction_id,category_id,amount',
                'splits.category:id,name,color',
            ])
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
        $account->current_balance = $this->balanceMap(collect([$account]))[$account->id]
            ?? (string) $account->starting_balance;

        $transactions = Transaction::query()
            ->where(function ($query) use ($account) {
                $query->where('account_id', $account->id)
                    ->orWhere('transfer_to_account_id', $account->id);
            })
            ->with([
                'category:id,name,color',
                'transferToAccount:id,name',
                'account:id,name',
                'splits:id,transaction_id,category_id,amount',
                'splits.category:id,name,color',
            ])
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
        $categories = Category::select('id', 'name', 'type', 'color')
            ->where('is_system', false)
            ->orderBy('name')
            ->get();

        return Inertia::render('Accounts/Show', [
            'account' => $account,
            'transactions' => $transactions,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    /**
     * Compute current balances for many accounts in a constant number of queries.
     * Returns ['<account_id>' => '<balance string>'].
     *
     * Two grouped sums are used: total of own-account transactions (which are
     * already signed) and total of inbound transfers (negative on the source
     * row), then combined with the starting balance per account.
     */
    private function balanceMap(Collection $accounts): array
    {
        $ids = $accounts->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }

        $outgoing = Transaction::whereIn('account_id', $ids)
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(amount) as total')
            ->pluck('total', 'account_id');

        $inbound = Transaction::whereIn('transfer_to_account_id', $ids)
            ->groupBy('transfer_to_account_id')
            ->selectRaw('transfer_to_account_id, SUM(amount) as total')
            ->pluck('total', 'transfer_to_account_id');

        $map = [];
        foreach ($accounts as $account) {
            $balance = bcadd((string) $account->starting_balance, (string) ($outgoing[$account->id] ?? '0'), 2);
            // Inbound transfers are negative on the source row → subtract to add their absolute value.
            $balance = bcsub($balance, (string) ($inbound[$account->id] ?? '0'), 2);
            $map[$account->id] = $balance;
        }

        return $map;
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
