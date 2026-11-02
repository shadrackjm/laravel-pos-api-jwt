<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        // withCount instead of loading every product just to count them.
        $categories = Category::withCount('products')->orderBy('name')->get();

        return $this->success($categories->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'products_count' => $c->products_count,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ]);

        return $this->success(Category::create($validated), 'Category created.', 201);
    }

    public function show(Category $category): JsonResponse
    {
        return $this->success([
            'id' => $category->id,
            'name' => $category->name,
            'products_count' => $category->products()->count(),
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name,'.$category->id],
        ]);

        $category->update($validated);

        return $this->success($category, 'Category updated.');
    }

    public function destroy(Category $category): JsonResponse
    {
        // Deleting a category cascades to its products (see the migration).
        // Refusing is friendlier than silently destroying the catalogue.
        if ($category->products()->exists()) {
            return $this->error('Cannot delete a category that still has products.', 422);
        }

        $category->delete();

        return $this->success(null, 'Category deleted.');
    }
}
