<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            // The uniqueness check must ignore THIS product, or saving a product
            // without changing its SKU fails against itself.
            'sku' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
