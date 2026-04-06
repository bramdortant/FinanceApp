<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::with('parent')
            ->withCount('transactions')
            ->orderBy('name')
            ->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Categories/Create', [
            'parentCategories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return Redirect::route('categories.index');
    }

    public function edit(Category $category): Response
    {
        $excludedIds = $this->getDescendantIds($category->id);
        $excludedIds[] = $category->id;

        return Inertia::render('Categories/Edit', [
            'category' => $category->load('parent'),
            'parentCategories' => Category::whereNotIn('id', $excludedIds)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return Redirect::route('categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->transactions()->exists()) {
            return Redirect::route('categories.index')
                ->with('error', 'Kan een categorie met transacties niet verwijderen.');
        }

        $category->delete();

        return Redirect::route('categories.index');
    }

    private function getDescendantIds(int $categoryId): array
    {
        $descendants = [];
        $children = Category::where('parent_id', $categoryId)->pluck('id');

        foreach ($children as $childId) {
            $descendants[] = $childId;
            $descendants = array_merge($descendants, $this->getDescendantIds($childId));
        }

        return $descendants;
    }
}
