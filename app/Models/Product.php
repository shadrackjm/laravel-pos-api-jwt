<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'name', 'sku', 'price', 'stock'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            // 'decimal:2' keeps the value a STRING. Casting money to float here is
            // exactly the bug episode 16 is about — don't "fix" this to 'float'.
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The price in whole cents, for arithmetic. All money maths in this app goes
     * through integers — see episode 16.
     */
    public function priceInCents(): int
    {
        return (int) round((float) $this->price * 100);
    }
}
