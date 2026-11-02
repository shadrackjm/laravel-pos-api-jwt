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

        return $this->success(
            new SaleResource($sale->load(['items', 'user', 'customer'])),
            'Sale completed.',
            201
        );
    }

    public function show(Sale $sale): JsonResponse
    {
        return $this->success(new SaleResource($sale->load(['items', 'user', 'customer'])));
    }
}
