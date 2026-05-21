<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function dailySales(): JsonResponse
    {
        $this->checkPermission();

        $date = request()->input('date') ? Carbon::parse(request()->input('date')) : null;
        $report = $this->reportService->getDailySalesReport($date);

        return response()->json($report);
    }

    public function monthlySales(): JsonResponse
    {
        $this->checkPermission();

        $year = request()->input('year', Carbon::now()->year);
        $month = request()->input('month', Carbon::now()->month);

        $report = $this->reportService->getMonthlySalesReport($year, $month);

        return response()->json($report);
    }

    public function productSales(): JsonResponse
    {
        $this->checkPermission();

        $startDate = request()->input('start_date') ? Carbon::parse(request()->input('start_date')) : null;
        $endDate = request()->input('end_date') ? Carbon::parse(request()->input('end_date')) : null;

        $report = $this->reportService->getProductSalesReport($startDate, $endDate);

        return response()->json($report);
    }

    public function inventoryValuation(): JsonResponse
    {
        $this->checkPermission();

        $report = $this->reportService->getInventoryValuation();

        return response()->json($report);
    }

    public function profitLoss(): JsonResponse
    {
        $this->checkPermission();

        $start = request()->input('start_date')
            ? Carbon::parse(request()->input('start_date'))
            : Carbon::now()->startOfMonth();
        $end = request()->input('end_date')
            ? Carbon::parse(request()->input('end_date'))
            : Carbon::now()->endOfMonth();
        $includeCogs = request()->boolean('include_cogs', true);

        return response()->json($this->reportService->getProfitLossReport($start, $end, $includeCogs));
    }

    public function inventoryTransactions(): JsonResponse
    {
        $this->checkPermission();

        $query = \App\Models\InventoryTransaction::with(['ingredient', 'user'])
            ->orderByDesc('created_at');

        if (request()->input('date_from')) {
            $query->whereDate('created_at', '>=', request()->input('date_from'));
        }
        if (request()->input('date_to')) {
            $query->whereDate('created_at', '<=', request()->input('date_to'));
        }
        if (request()->input('type')) {
            $query->where('type', request()->input('type'));
        }
        if (request()->input('ingredient_id')) {
            $query->where('ingredient_id', request()->input('ingredient_id'));
        }

        return response()->json($query->paginate(50));
    }

    private function checkPermission(): void
    {
        if (! auth()->user()?->hasAnyRole('admin') && ! auth()->user()?->hasPermissionTo('view reports')) {
            abort(403, 'Unauthorized');
        }
    }
}
