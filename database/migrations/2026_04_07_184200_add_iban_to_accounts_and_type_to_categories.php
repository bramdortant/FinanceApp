<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Stored as TEXT because Laravel's encrypted cast produces ciphertext
            // that easily exceeds the 255-char varchar default.
            $table->text('iban')->nullable()->after('currency');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('type')->default('expense')->after('name');
        });

        // Drop old triggers — SQLite recreates the table when ->change() is used,
        // and the triggers would otherwise be lost or duplicated.
        DB::statement('DROP TRIGGER IF EXISTS check_transaction_type_insert');
        DB::statement('DROP TRIGGER IF EXISTS check_transaction_type_update');

        Schema::table('transactions', function (Blueprint $table) {
            // Encrypted ciphertext does not fit in a 255-char column.
            $table->text('counterparty_name')->nullable()->change();
            $table->text('counterparty_iban')->nullable()->change();
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

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('iban');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        DB::statement('DROP TRIGGER IF EXISTS check_transaction_type_insert');
        DB::statement('DROP TRIGGER IF EXISTS check_transaction_type_update');

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('counterparty_name')->nullable()->change();
            $table->string('counterparty_iban')->nullable()->change();
        });
    }
};
