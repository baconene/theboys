<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

class ReportService
{
    public function getDailySalesReport(Carbon $date = null): array
    {
        $date ??= Carbon::today();

        $orders = Order::whereDate('created_at', $date->toDateString())
            ->where('payment_status', 'paid')
            ->get();

        return [
            'date' => $date->format('Y-m-d'),
            'total_orders' => $orders->count(),
            'total_sales' => $orders->sum('total_amount'),
            'total_discount' => $orders->sum('discount_amount'),
            'total_tax' => $orders->sum('tax_amount'),
            'orders' => $orders,
        ];
    }

    public function getMonthlySalesReport(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        $orders = Order::whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->get();

        return [
            'month' => $start->format('Y-m'),
            'total_orders' => $orders->count(),
            'total_sales' => $orders->sum('total_amount'),
            'total_discount' => $orders->sum('discount_amount'),
            'total_tax' => $orders->sum('tax_amount'),
        ];
    }

    public function getProductSalesReport(Carbon $startDate = null, Carbon $endDate = null)
    {
        $startDate ??= Carbon::now()->startOfMonth();
        $endDate ??= Carbon::now()->endOfMonth();

        return \App\Models\OrderItem::join('products', 'order_items.product_id', '=', 'products.id')
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->selectRaw('order_items.product_id, products.name as product_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.subtotal) as total_sales')
            ->groupBy('order_items.product_id', 'products.name')
            ->orderByDesc('total_sales')
            ->get();
    }

    public function getInventoryValuation()
    {
        return \App\Models\Ingredient::where('is_active', true)
            ->selectRaw('*, (current_quantity) as valuation')
            ->get();
    }

    public function getProfitLossReport(Carbon $start, Carbon $end, bool $includeCogs = true): array
    {
        // Revenue from paid orders
        $revenue = \App\Models\Order::whereBetween('created_at', [$start->startOfDay(), $end->copy()->endOfDay()])
            ->where('payment_status', 'paid')
            ->selectRaw('
                COUNT(*) as order_count,
                COALESCE(SUM(total_amount), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as discounts
            ')
            ->first();

        $grossSales = (float) ($revenue->gross_sales ?? 0);
        $discounts  = (float) ($revenue->discounts ?? 0);
        $netRevenue = $grossSales - $discounts;

        // COGS: sum of cost_subtotal on items from paid orders in period
        $cogs = (float) \App\Models\OrderItem::whereHas('order', fn ($q) => $q
            ->whereBetween('created_at', [$start->startOfDay(), $end->copy()->endOfDay()])
            ->where('payment_status', 'paid')
        )->sum('cost_subtotal');

        // Adjust COGS based on toggle: if includeCogs is false, don't deduct it from gross profit
        $deductedCogs = $includeCogs ? $cogs : 0;
        $grossProfit  = $netRevenue - $deductedCogs;
        $grossMargin  = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0;

        // Operating expenses (exclude COGS entries when includeCogs is false)
        $expenseQuery = \App\Models\FinancialTransaction::where('type', 'expense')
            ->whereBetween('transacted_at', [$start->startOfDay(), $end->copy()->endOfDay()]);
        
        if (!$includeCogs) {
            $expenseQuery->where('description', 'not like', 'COGS:%');
        }
        
        $expenseRows = $expenseQuery->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as count')
            ->first();

        $totalExpenses = (float) ($expenseRows->total ?? 0);
        $expenseCount  = (int)   ($expenseRows->count ?? 0);

        // Expense breakdown (exclude COGS entries when includeCogs is false)
        $expenseBreakdownQuery = \App\Models\FinancialTransaction::where('type', 'expense')
            ->whereBetween('transacted_at', [$start->startOfDay(), $end->copy()->endOfDay()]);
        
        if (!$includeCogs) {
            $expenseBreakdownQuery->where('description', 'not like', 'COGS:%');
        }
        
        $expenseBreakdown = $expenseBreakdownQuery
            ->orderByDesc('transacted_at')
            ->get(['description', 'amount', 'transacted_at'])
            ->map(fn ($e) => [
                'description'   => $e->description,
                'amount'        => (float) $e->amount,
                'transacted_at' => $e->transacted_at,
            ]);

        // Income adjustments (credit entries recorded manually)
        $incomeAdjRows = \App\Models\FinancialTransaction::where('type', 'income_adjustment')
            ->whereBetween('transacted_at', [$start->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as count')
            ->first();

        $totalIncomeAdj = (float) ($incomeAdjRows->total ?? 0);

        $incomeAdjBreakdown = \App\Models\FinancialTransaction::where('type', 'income_adjustment')
            ->whereBetween('transacted_at', [$start->startOfDay(), $end->copy()->endOfDay()])
            ->orderByDesc('transacted_at')
            ->get(['description', 'amount', 'transacted_at'])
            ->map(fn ($e) => [
                'description'   => $e->description,
                'amount'        => (float) $e->amount,
                'transacted_at' => $e->transacted_at,
            ]);

        // Payroll disbursements
        $payrollRows = \App\Models\FinancialTransaction::where('type', 'payroll')
            ->whereBetween('transacted_at', [$start->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as count')
            ->first();

        $totalPayroll = (float) ($payrollRows->total ?? 0);

        $payrollBreakdown = \App\Models\FinancialTransaction::where('type', 'payroll')
            ->whereBetween('transacted_at', [$start->startOfDay(), $end->copy()->endOfDay()])
            ->orderByDesc('transacted_at')
            ->get(['description', 'amount', 'transacted_at'])
            ->map(fn ($e) => [
                'description'   => $e->description,
                'amount'        => (float) $e->amount,
                'transacted_at' => $e->transacted_at,
            ]);

        $netProfit = $grossProfit + $totalIncomeAdj - $totalExpenses - $totalPayroll;
        $totalRevenuePlusAdj = $netRevenue + $totalIncomeAdj;
        $netMargin = $totalRevenuePlusAdj > 0 ? round(($netProfit / $totalRevenuePlusAdj) * 100, 2) : 0;

        // Flag if COGS data is incomplete (orders exist but all have zero cost)
        $hasCogs = $cogs > 0;
        $paidOrderCount = (int) ($revenue->order_count ?? 0);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
            'revenue' => [
                'order_count' => $paidOrderCount,
                'gross_sales' => $grossSales,
                'discounts'   => $discounts,
                'net_revenue' => $netRevenue,
            ],
            'cogs' => [
                'total'    => $cogs,
                'has_data' => $hasCogs,
            ],
            'gross_profit' => $grossProfit,
            'gross_margin' => $grossMargin,
            'income_adjustments' => [
                'total'     => $totalIncomeAdj,
                'count'     => (int) ($incomeAdjRows->count ?? 0),
                'breakdown' => $incomeAdjBreakdown,
            ],
            'expenses' => [
                'total'     => $totalExpenses,
                'count'     => $expenseCount,
                'breakdown' => $expenseBreakdown,
            ],
            'payroll' => [
                'total'     => $totalPayroll,
                'count'     => (int) ($payrollRows->count ?? 0),
                'breakdown' => $payrollBreakdown,
            ],
            'net_profit' => $netProfit,
            'net_margin' => $netMargin,
            'include_cogs' => $includeCogs,
        ];
    }
}
