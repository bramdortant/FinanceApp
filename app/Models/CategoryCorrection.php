<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records every manual category change on a transaction. These corrections
 * serve as training data for AI auto-categorization (Phase 9) — each one
 * tells the AI "for this description, the user preferred this category."
 */
class CategoryCorrection extends Model
{
    protected $fillable = [
        'transaction_id',
        'old_category_id',
        'new_category_id',
        'description',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function oldCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'old_category_id');
    }

    public function newCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'new_category_id');
    }
}
