<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'name',
        'type',
        'starting_balance',
        'currency',
        'icon',
    ];

    protected $casts = [
        'starting_balance' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function csvImports(): HasMany
    {
        return $this->hasMany(CsvImport::class);
    }
}
