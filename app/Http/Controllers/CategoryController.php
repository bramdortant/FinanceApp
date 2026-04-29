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
        $categories = Category::where('is_system', false)
            ->withCount(['transactions', 'splits'])
            ->orderBy('name')
            ->get();

        // Combine direct transaction count with split usages so the index
        // reflects total usage and the delete button is disabled correctly.
        $categories->each(function (Category $category) {
            $category->transactions_count += $category->splits_count;
            unset($category->splits_count);
        });

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Categories/Create');
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return Redirect::route('categories.index')
            ->with('success', 'Categorie aangemaakt.');
    }

    public function edit(Category $category): Response|RedirectResponse
    {
        if ($category->is_system) {
            return Redirect::route('categories.index')
                ->with('error', 'Systeemcategorieën kunnen niet worden bewerkt.');
        }

        return Inertia::render('Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        if ($category->is_system) {
            return Redirect::route('categories.index')
                ->with('error', 'Systeemcategorieën kunnen niet worden bewerkt.');
        }
        // Prevent changing income/expense type when transactions or splits
        // already reference this category — it would leave them with a
        // category whose type no longer matches the parent transaction's type.
        if (
            $category->type !== $request->validated('type')
            && ($category->transactions()->exists() || $category->splits()->exists())
        ) {
            return Redirect::back()
                ->withErrors(['type' => 'Kan het type niet wijzigen zolang er transacties aan deze categorie gekoppeld zijn.']);
        }

        $category->update($request->validated());

        return Redirect::route('categories.index')
            ->with('success', 'Categorie bijgewerkt.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->is_system) {
            return Redirect::route('categories.index')
                ->with('error', 'Systeemcategorieën kunnen niet worden verwijderd.');
        }

        if ($category->transactions()->exists() || $category->splits()->exists()) {
            return Redirect::route('categories.index')
                ->with('error', 'Kan een categorie met transacties niet verwijderen.');
        }

        $category->delete();

        return Redirect::route('categories.index')
            ->with('success', 'Categorie verwijderd.');
    }

}
