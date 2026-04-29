<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'type',
        'color',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }
}
