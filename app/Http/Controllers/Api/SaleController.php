<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends ApiController
{
    public function __construct(private CheckoutService $checkout) {}

    public function index(Request $request): JsonResponse
    {
        $sales = Sale::query()
            ->with(['user:id,name', 'customer:id,name,phone'])
            // Episode 15 — a cashier sees only their own sales; a manager sees all.
            // This is the scoping that stops one cashier reading another's takings.
            ->unless(auth()->user()->isManager(), fn ($q) => $q->where('user_id', auth()->id()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            $sales->setCollection(SaleResource::collection($sales->getCollection())->collection)
        );
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->checkout->checkout(
            cashier: auth()->user(),
            items: $request->validated('items'),
            customerId: $request->validated('customer_id'),
        );

        // Episode 19 — anything that talks to the outside world happens HERE,
        // after the transaction has committed. A receipt printer or SMS call
        // inside the transaction would hold database locks for the length of a
        // network round trip, and could succeed just before a rollback — sending
        // a customer a receipt for a sale that no longer exists.
        //
        // rescue() means a failing side effect can never fail the sale itself.
        rescue(fn () => $this->sendReceipt($sale), report: false);

        return $this->success(
            new SaleResource($sale->load(['items', 'user', 'customer'])),
            'Sale completed.',
            201
        );
    }

    public function show(Sale $sale): JsonResponse
    {
        // Route-model binding resolved this by uuid. It does NOT check ownership,
        // so we must — otherwise any authenticated cashier can read any sale
        // just by holding its uuid. That is the IDOR bug from episode 15.
        if (! auth()->user()->isManager() && $sale->user_id !== auth()->id()) {
            // 404, not 403. A 403 confirms the sale exists, which is itself a leak.
            return $this->error('Sale not found.', 404);
        }

        return $this->success(new SaleResource($sale->load(['items', 'user', 'customer'])));
    }

    private function sendReceipt(Sale $sale): void
    {
        // Stand-in for a real printer / SMS / e-mail integration.
        logger()->info('Receipt issued', ['receipt' => $sale->receipt_number]);
    }
}
