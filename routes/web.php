<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
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
    Route::post('csv-imports/preview', [CsvImportController::class, 'preview'])->name('csv-imports.preview');
    Route::post('csv-imports', [CsvImportController::class, 'store'])->name('csv-imports.store');

    Route::post('accounts/{account}/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
