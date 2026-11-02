<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Note: no `id`. The public identifier is the uuid — see episode 15.
            'uuid' => $this->uuid,
            'receipt_number' => $this->receipt_number,
            'total' => (string) $this->total,
            'status' => $this->status,
            'cashier' => $this->whenLoaded('user', fn () => $this->user->name),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer?->only(['name', 'phone'])),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
