<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryRuleController extends Controller
{
    public function index(): Response
    {
        $rules = CategoryRule::with('category')
            ->orderBy('match_pattern')
            ->get();

        // Detect conflicts: rules whose patterns are substrings of other rules.
        // When both match, the longer one wins — but the user should know.
        $conflicts = [];
        foreach ($rules as $rule) {
            foreach ($rules as $other) {
                if ($rule->id === $other->id) {
                    continue;
                }
                if (
                    str_contains(mb_strtolower($other->match_pattern), mb_strtolower($rule->match_pattern))
                    && $rule->category_id !== $other->category_id
                ) {
                    $conflicts[$rule->id] = $other->id;
                }
            }
        }

        $categories = Category::select('id', 'name', 'type', 'color')
            ->where('is_system', false)
            ->orderBy('name')
            ->get();

        return Inertia::render('CategoryRules/Index', [
            'rules' => $rules,
            'conflicts' => $conflicts,
            'categories' => $categories,
        ]);
    }

    /**
     * Create a new category rule. Called inline during CSV import
     * when the user manually assigns a category and confirms the
     * "Altijd categoriseren als...?" prompt.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'match_pattern' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
        ]);

        $existing = CategoryRule::where('match_pattern', $validated['match_pattern'])->first();

        if ($existing) {
            $existing->update(['category_id' => $validated['category_id']]);
            $rule = $existing->load('category');
        } else {
            $rule = CategoryRule::create($validated)->load('category');
        }

        if ($request->wantsJson()) {
            return response()->json(['rule' => $rule], $existing ? 200 : 201);
        }

        return Redirect::route('category-rules.index')
            ->with('success', 'Regel aangemaakt.');
    }

    public function update(Request $request, CategoryRule $categoryRule): RedirectResponse
    {
        $validated = $request->validate([
            'match_pattern' => [
                'required', 'string', 'max:255',
                Rule::unique('category_rules')->ignore($categoryRule->id),
            ],
            'category_id' => ['required', 'exists:categories,id'],
        ]);

        $categoryRule->update($validated);

        return Redirect::route('category-rules.index')
            ->with('success', 'Regel bijgewerkt.');
    }

    public function destroy(CategoryRule $categoryRule): RedirectResponse
    {
        $categoryRule->delete();

        return Redirect::route('category-rules.index')
            ->with('success', 'Regel verwijderd.');
    }
}
