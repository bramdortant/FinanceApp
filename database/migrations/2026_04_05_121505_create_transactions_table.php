<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->string('original_description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->foreignId('transfer_to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('csv_import_hash')->nullable()->index();
            $table->foreignId('csv_import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
