<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The heart of the POS. Episodes 13, 16, 17 and 19.
 *
 * Read the comments as the syllabus — each one is a bug this would have shipped
 * without the line beneath it.
 */
class CheckoutService
{
    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function checkout(User $cashier, array $items, ?int $customerId = null): Sale
    {
        return DB::transaction(function () use ($cashier, $items, $customerId) {
            $totalCents = 0;
            $lines = [];

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Reject rather than silently under-deduct. Letting the sale
                // through and clamping stock at zero means the books say you sold
                // five and the shelf says you had three, with no error anywhere.
                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->name}. Available: {$product->stock}, requested: {$item['quantity']}."],
                    ]);
                }

                $unitCents = $product->priceInCents();
                $lineCents = $unitCents * $item['quantity'];
                $totalCents += $lineCents;

                $lines[] = [
                    'product_id' => $product->id,
                    // Frozen at the moment of sale. The product may later be
                    // renamed, repriced or deleted; this receipt must not change.
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $this->centsToDecimal($unitCents),
                    'line_total' => $this->centsToDecimal($lineCents),
                ];

                // decrement() issues `stock = stock - N` as a single SQL statement,
                // so the database does the arithmetic.
                $product->decrement('stock', $item['quantity']);
            }

            $sale = Sale::create([
                'uuid' => (string) Str::uuid(),
                'receipt_number' => $this->nextReceiptNumber(),
                // Episode 13 — the cashier comes from the verified token, never
                // from the request body. A client must not be able to attribute
                // a sale to someone else.
                'user_id' => $cashier->id,
                'customer_id' => $customerId,
                // Likewise the total. If we trusted a client-supplied total, a
                // modified app could pay one cent for a full basket.
                'total' => $this->centsToDecimal($totalCents),
                'status' => 'completed',
            ]);

            $sale->items()->createMany($lines);

            return $sale->load('items');
        });
    }

    private function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function nextReceiptNumber(): string
    {
        return 'R-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
