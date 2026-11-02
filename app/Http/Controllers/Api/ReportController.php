<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends ApiController
{
    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $base = Sale::query()
            ->where('status', 'completed')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));

        // One query for both aggregates instead of two round trips.
        $totals = (clone $base)
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        // Episode 22 — the best-sellers list is where a naive implementation
        // loads every sale item into PHP and sums them in a loop. Let the
        // database do the aggregation; it is built for exactly this.
        $topProducts = SaleItem::query()
            ->select('product_name', DB::raw('SUM(quantity) as units_sold'), DB::raw('SUM(line_total) as revenue'))
            ->whereIn('sale_id', (clone $base)->select('id'))
            ->groupBy('product_name')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get();

        return $this->success([
            'sales_count' => (int) $totals->sales_count,
            'revenue' => number_format((float) $totals->revenue, 2, '.', ''),
            'top_products' => $topProducts->map(fn ($row) => [
                'product_name' => $row->product_name,
                'units_sold' => (int) $row->units_sold,
                'revenue' => number_format((float) $row->revenue, 2, '.', ''),
            ]),
        ]);
    }
}
