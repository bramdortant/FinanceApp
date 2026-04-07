<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:income,expense'],
            'parent_id' => ['nullable', 'exists:categories,id', function ($attribute, $value, $fail) {
                if ($value === null) {
                    return;
                }

                $category = $this->route('category');

                if (! $category) {
                    return;
                }

                if ((int) $value === $category->id) {
                    $fail('Een categorie kan niet zijn eigen hoofdcategorie zijn.');

                    return;
                }

                if ($this->wouldCreateLoop($category->id, (int) $value)) {
                    $fail('Deze hoofdcategorie zou een circulaire keten maken.');
                }
            }],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    private function wouldCreateLoop(int $categoryId, int $parentId): bool
    {
        $visited = [$categoryId];
        $currentId = $parentId;

        while ($currentId !== null) {
            if (in_array($currentId, $visited)) {
                return true;
            }

            $visited[] = $currentId;
            $currentId = Category::where('id', $currentId)->value('parent_id');
        }

        return false;
    }
}
