<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'receipt_number', 'user_id', 'customer_id', 'total', 'status'])]
class Sale extends Model
{
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
        ];
    }

    /**
     * Bind {sale} in routes by uuid, not id. Sequential IDs are guessable, and a
     * guessable ID is half of an IDOR bug — see episode 15.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
