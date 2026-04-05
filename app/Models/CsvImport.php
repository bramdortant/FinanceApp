<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CsvImport extends Model
{
    protected $fillable = [
        'filename',
        'account_id',
        'row_count',
        'imported_count',
        'skipped_count',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'imported_count' => 'integer',
        'skipped_count' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
