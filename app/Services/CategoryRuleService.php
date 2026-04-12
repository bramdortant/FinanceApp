<?php

namespace App\Services;

use App\Models\CategoryRule;
use Illuminate\Support\Collection;

/**
 * Applies category rules to transaction descriptions. Rules are simple
 * case-insensitive substring matches: if the pattern appears anywhere in
 * the description (or original_description), the rule matches.
 *
 * When multiple rules match, the longest pattern wins — a more specific
 * match is assumed to be more accurate. For example, "Albert Heijn" beats
 * "Albert" when both match "Albert Heijn Amsterdam".
 */
class CategoryRuleService
{
    /**
     * Find the best matching rule for a transaction description.
     */
    public function match(string $description, ?string $originalDescription = null): ?CategoryRule
    {
        $rules = CategoryRule::with('category')->get();
        $searchText = mb_strtolower($description . ' ' . ($originalDescription ?? ''));

        return $this->findBestMatch($searchText, $rules);
    }

    /**
     * Apply rules to an array of parsed CSV rows. Each row gets a
     * `matched_category_id` and `matched_rule_id` field added.
     *
     * Also increments match_count on matched rules (batched at the end).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function applyToRows(array $rows): array
    {
        $rules = CategoryRule::with('category')->get();
        $matchCounts = [];

        foreach ($rows as &$row) {
            $searchText = mb_strtolower(
                ($row['description'] ?? '') . ' ' . ($row['original_description'] ?? '')
            );

            $bestMatch = $this->findBestMatch($searchText, $rules);

            if ($bestMatch) {
                $row['matched_category_id'] = $bestMatch->category_id;
                $row['matched_rule_id'] = $bestMatch->id;
                $matchCounts[$bestMatch->id] = ($matchCounts[$bestMatch->id] ?? 0) + 1;
            } else {
                $row['matched_category_id'] = null;
                $row['matched_rule_id'] = null;
            }
        }

        // Batch-update match counts.
        foreach ($matchCounts as $ruleId => $count) {
            CategoryRule::where('id', $ruleId)->increment('match_count', $count);
        }

        return $rows;
    }

    /**
     * Find the rule with the longest matching pattern in the search text.
     * Longer patterns are more specific, so they take priority.
     */
    private function findBestMatch(string $searchText, Collection $rules): ?CategoryRule
    {
        $bestMatch = null;
        $bestLength = 0;

        foreach ($rules as $rule) {
            $pattern = mb_strtolower($rule->match_pattern);

            if (str_contains($searchText, $pattern) && mb_strlen($pattern) > $bestLength) {
                $bestMatch = $rule;
                $bestLength = mb_strlen($pattern);
            }
        }

        return $bestMatch;
    }
}
