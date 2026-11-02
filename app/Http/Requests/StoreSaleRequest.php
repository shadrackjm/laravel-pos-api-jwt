<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            // Deliberately absent: `total`, `unit_price`, `user_id`.
            // Anything to do with money or identity is computed on the server.
            // If the client could send a total, it could send a total of 0.01.
        ];
    }
}
