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
        Schema::create('csv_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->integer('row_count')->default(0);
            $table->integer('imported_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_imports');
    }
};
