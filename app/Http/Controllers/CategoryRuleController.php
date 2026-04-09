<?php

namespace App\Http\Controllers;

use App\Models\CategoryRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryRuleController extends Controller
{
    /**
     * Create a new category rule. Called inline during CSV import
     * when the user manually assigns a category and confirms the
     * "Altijd categoriseren als...?" prompt.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'match_pattern' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
        ]);

        // Check for existing rule with the same pattern to avoid duplicates.
        $existing = CategoryRule::where('match_pattern', $validated['match_pattern'])->first();

        if ($existing) {
            $existing->update(['category_id' => $validated['category_id']]);

            return response()->json(['rule' => $existing->load('category')]);
        }

        $rule = CategoryRule::create($validated);

        return response()->json(['rule' => $rule->load('category')], 201);
    }
}
