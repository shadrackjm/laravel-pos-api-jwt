<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // The frozen snapshot, not $this->product->name. The product may be
            // renamed or deleted; the receipt must not change retroactively.
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'line_total' => (string) $this->line_total,
        ];
    }
}
