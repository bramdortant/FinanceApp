<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryRule extends Model
{
    protected $fillable = [
        'match_pattern',
        'category_id',
        'match_count',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Look up an existing rule by pattern, case-insensitively. Matching is
     * case-insensitive at runtime (CategoryRuleService), so two rules
     * differing only in case would behave identically and confuse the user.
     */
    public static function findByPattern(string $pattern): ?self
    {
        return static::whereRaw('LOWER(match_pattern) = ?', [mb_strtolower($pattern)])->first();
    }

    /**
     * Create or update a rule, treating patterns as case-insensitive. When
     * an existing rule is found, its display pattern is refreshed to the
     * incoming casing — the latest user input wins for display.
     */
    public static function upsertByPattern(string $pattern, int $categoryId): self
    {
        $existing = static::findByPattern($pattern);

        if ($existing) {
            $existing->update([
                'match_pattern' => $pattern,
                'category_id' => $categoryId,
            ]);
            return $existing;
        }

        return static::create([
            'match_pattern' => $pattern,
            'category_id' => $categoryId,
        ]);
    }
}
