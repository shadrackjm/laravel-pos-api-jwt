<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort' => ['nullable', 'string', 'in:name,price,stock,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            // Capped. Without a max, `?per_page=100000` is a free denial of service.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            // Episode 22 — without this, rendering N products fires N extra
            // queries for their categories. One eager load fixes it.
            ->with('category')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search')->toString();
                // Escape the LIKE wildcards, or a search for "100%" matches
                // everything and a search for "_" matches every single character.
                $escaped = addcslashes($term, '%_\\');
                $query->where('name', 'like', "%{$escaped}%");
            })
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->boolean('in_stock'), fn ($q) => $q->where('stock', '>', 0))
            // Whitelisted by the validator above. Passing user input straight
            // into orderBy() is an SQL injection vector.
            ->orderBy($request->input('sort', 'name'), $request->input('direction', 'asc'))
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return $this->paginated(
            $products->setCollection(ProductResource::collection($products->getCollection())->collection)
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return $this->success(new ProductResource($product->load('category')), 'Product created.', 201);
    }

    public function show(Product $product): JsonResponse
    {
        return $this->success(new ProductResource($product->load('category')));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return $this->success(new ProductResource($product->load('category')), 'Product updated.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return $this->success(null, 'Product deleted.');
    }
}
