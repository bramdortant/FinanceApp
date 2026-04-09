<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_system flag so system categories (Overboeking,
        // Ongecategoriseerd) can be hidden from normal management.
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('color');
        });

        $now = now()->toDateTimeString();

        // "Overboeking" — assigned automatically to transfer transactions.
        $transferCategoryId = DB::table('categories')->insertGetId([
            'name' => 'Overboeking',
            'type' => 'expense',
            'color' => '#9CA3AF',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // "Ongecategoriseerd" — temporary fallback for existing transactions
        // that were imported before categories became mandatory.
        $uncategorizedId = DB::table('categories')->insertGetId([
            'name' => 'Ongecategoriseerd',
            'type' => 'expense',
            'color' => '#6B7280',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Assign system categories to existing transactions.
        DB::table('transactions')
            ->where('type', 'transfer')
            ->update(['category_id' => $transferCategoryId]);

        DB::table('transactions')
            ->whereNull('category_id')
            ->update(['category_id' => $uncategorizedId]);

        // NOTE: category_id stays nullable at the DB level because SQLite
        // cannot alter column constraints. Non-null is enforced by
        // application-level validation (TransactionRequest, CsvImportService).
    }

    public function down(): void
    {
        // Clear system category assignments.
        DB::table('transactions')
            ->whereIn('category_id', function ($query) {
                $query->select('id')->from('categories')->where('is_system', true);
            })
            ->update(['category_id' => null]);

        DB::table('categories')->where('is_system', true)->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
