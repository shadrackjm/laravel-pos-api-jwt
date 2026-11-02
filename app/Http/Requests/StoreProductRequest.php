<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already established who may reach this endpoint.
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            // 'decimal:0,2' rejects 12.999 — which would silently round into a
            // price the shop never set. 'numeric' alone would accept it.
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
            'stock' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'A product with this SKU already exists.',
            'price.decimal' => 'Price may have at most 2 decimal places.',
            'price.min' => 'Price cannot be negative.',
        ];
    }
}
