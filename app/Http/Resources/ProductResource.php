<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            // Money goes out as a STRING, never a JSON number.
            //
            // JSON numbers are IEEE-754 doubles. A JavaScript client parsing
            // 12.50 gets a float straight back into the same rounding problem
            // we just avoided on the server. A string crosses the wire exactly.
            'price' => (string) $this->price,
            'stock' => $this->stock,
            'in_stock' => $this->stock > 0,
            // whenLoaded() is the N+1 guard (episode 22): if the caller did not
            // eager-load the category, we omit it rather than firing a query
            // per product to fetch it.
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
