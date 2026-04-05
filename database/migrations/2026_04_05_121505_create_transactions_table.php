<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_iban')->nullable();
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->string('transaction_code', 10)->nullable();
            $table->text('notes')->nullable();
            $table->string('csv_import_hash')->nullable()->index();
            $table->foreignId('csv_import_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("
            CREATE TRIGGER check_transaction_type_insert
            BEFORE INSERT ON transactions
            BEGIN
                SELECT CASE
                    WHEN NEW.type NOT IN ('income', 'expense', 'transfer')
                    THEN RAISE(ABORT, 'type must be income, expense, or transfer')
                END;
                SELECT CASE
                    WHEN NEW.type = 'transfer' AND NEW.transfer_to_account_id IS NULL
                    THEN RAISE(ABORT, 'transfer transactions require transfer_to_account_id')
                    WHEN NEW.type != 'transfer' AND NEW.transfer_to_account_id IS NOT NULL
                    THEN RAISE(ABORT, 'only transfer transactions can have transfer_to_account_id')
                END;
            END;
        ");

        DB::statement("
            CREATE TRIGGER check_transaction_type_update
            BEFORE UPDATE ON transactions
            BEGIN
                SELECT CASE
                    WHEN NEW.type NOT IN ('income', 'expense', 'transfer')
                    THEN RAISE(ABORT, 'type must be income, expense, or transfer')
                END;
                SELECT CASE
                    WHEN NEW.type = 'transfer' AND NEW.transfer_to_account_id IS NULL
                    THEN RAISE(ABORT, 'transfer transactions require transfer_to_account_id')
                    WHEN NEW.type != 'transfer' AND NEW.transfer_to_account_id IS NOT NULL
                    THEN RAISE(ABORT, 'only transfer transactions can have transfer_to_account_id')
                END;
            END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS check_transaction_type_insert');
        DB::statement('DROP TRIGGER IF EXISTS check_transaction_type_update');
        Schema::dropIfExists('transactions');
    }
};
