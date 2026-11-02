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
        // Episode 17 — the retry count is not decoration.
        //
        // We lock product rows in the order the CLIENT sent them. Two cashiers
        // checking out {mug, notebook} and {notebook, mug} at the same instant
        // each hold one lock and wait for the other: a deadlock. The database
        // picks a victim and rolls it back, which without a retry surfaces to
        // the cashier as a 500 on a sale that should simply have succeeded.
        //
        // Retrying the whole closure is safe because everything inside it is
        // database work that rolls back with it. Anything that is NOT safe to
        // repeat — printing, SMS, a payment gateway call — is deliberately kept
        // outside this closure. See episode 19.
        return DB::transaction(function () use ($cashier, $items, $customerId) {
            // Merge duplicate lines first. A client that sends the same product
            // twice would otherwise lock it twice and double-count the stock check.
            $quantities = [];
            foreach ($items as $item) {
                $id = (int) $item['product_id'];
                $quantities[$id] = ($quantities[$id] ?? 0) + (int) $item['quantity'];
            }

            // Episode 17 — the actual race.
            //
            // The naive version reads stock, compares it, then decrements. Between
            // the read and the write, another checkout can do exactly the same
            // thing: both see "1 left", both pass, both decrement. Stock goes to
            // -1 and the shop has sold an item it does not have. That gap is a
            // TOCTOU bug (time-of-check to time-of-use).
            //
            // lockForUpdate() makes the second transaction WAIT at this line until
            // the first one commits, so it reads the already-decremented value.
            //
            // ksort() gives every checkout the same lock ordering, which is what
            // actually prevents most deadlocks — the retry above is the safety net.
            ksort($quantities);

            $products = Product::whereIn('id', array_keys($quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Episode 16 — all money maths happens in whole cents.
            //
            // Floats cannot represent 0.1 exactly. Add ten of them and you get
            // 0.9999999999999999, not 1.0. On a busy till those fractions of a
            // cent accumulate until the drawer does not balance, and nobody can
            // work out why. Integers have no such problem.
            $totalCents = 0;
            $lines = [];

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => ["Product {$productId} does not exist."],
                    ]);
                }

                // Reject rather than silently under-deduct. Letting the sale
                // through and clamping stock at zero means the books say you sold
                // five and the shelf says you had three, with no error anywhere.
                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->name}. Available: {$product->stock}, requested: {$quantity}."],
                    ]);
                }

                $unitCents = $product->priceInCents();
                $lineCents = $unitCents * $quantity;
                $totalCents += $lineCents;

                $lines[] = [
                    'product_id' => $product->id,
                    // Frozen at the moment of sale. The product may later be
                    // renamed, repriced or deleted; this receipt must not change.
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $this->centsToDecimal($unitCents),
                    'line_total' => $this->centsToDecimal($lineCents),
                ];

                // decrement() issues `stock = stock - N` as a single SQL statement,
                // so the database does the arithmetic. We already hold the lock,
                // but this also avoids writing back a value we read earlier.
                $product->decrement('stock', $quantity);
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
        }, 3);
    }

    /**
     * Episode 20 — a refund puts stock back, inside its own transaction.
     */
    public function refund(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            // Re-read under a lock. Without this, two refund taps both see
            // "completed" and both give the stock back — a double refund.
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'completed') {
                throw ValidationException::withMessages([
                    'sale' => ['Only a completed sale can be refunded.'],
                ]);
            }

            foreach ($locked->items as $item) {
                // The product may have been deleted since the sale. The receipt
                // survives (product_name is frozen) but there is no stock to return.
                if ($item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $locked->update(['status' => 'refunded']);

            return $locked->load('items');
        }, 3);
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
