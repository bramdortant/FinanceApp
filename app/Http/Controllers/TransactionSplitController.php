<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionSplitRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class TransactionSplitController extends Controller
{
    /**
     * Replace all splits for a transaction. When splits exist, the
     * transaction's category_id is nulled — the splits are the source
     * of truth for categorization.
     */
    public function update(TransactionSplitRequest $request, Transaction $transaction): RedirectResponse
    {
        DB::transaction(function () use ($request, $transaction) {
            $transaction->splits()->delete();

            foreach ($request->validated('splits') as $split) {
                $transaction->splits()->create([
                    'category_id' => $split['category_id'],
                    'amount' => $split['amount'],
                ]);
            }

            $transaction->update(['category_id' => null]);
        });

        return Redirect::back()->with('success', 'Transactie gesplitst.');
    }

    /**
     * Remove all splits. The transaction is left without a category —
     * the user can re-categorize via the normal edit flow.
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        $transaction->splits()->delete();

        return Redirect::back()->with('success', 'Splits verwijderd.');
    }
}
