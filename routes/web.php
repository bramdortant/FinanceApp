<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryRuleController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('accounts/all', [AccountController::class, 'all'])->name('accounts.all');
    Route::resource('accounts', AccountController::class);
    Route::resource('categories', CategoryController::class)->except(['show']);

    Route::get('csv-imports/create', [CsvImportController::class, 'create'])->name('csv-imports.create');
    Route::post('csv-imports/upload', [CsvImportController::class, 'upload'])->name('csv-imports.upload');
    Route::get('csv-imports/{token}/preview', [CsvImportController::class, 'preview'])->name('csv-imports.preview');
    Route::post('csv-imports/{token}/create-accounts', [CsvImportController::class, 'createAccounts'])->name('csv-imports.create-accounts');
    Route::delete('csv-imports/{token}', [CsvImportController::class, 'cancel'])->name('csv-imports.cancel');
    Route::post('csv-imports', [CsvImportController::class, 'store'])->name('csv-imports.store');

    Route::get('category-rules', [CategoryRuleController::class, 'index'])->name('category-rules.index');
    Route::post('category-rules', [CategoryRuleController::class, 'store'])->name('category-rules.store');
    Route::put('category-rules/{categoryRule}', [CategoryRuleController::class, 'update'])->name('category-rules.update');
    Route::delete('category-rules/{categoryRule}', [CategoryRuleController::class, 'destroy'])->name('category-rules.destroy');

    Route::post('accounts/{account}/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
