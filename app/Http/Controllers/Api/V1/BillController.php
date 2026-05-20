<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\FinancialTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    public function index(): JsonResponse
    {
        $this->checkAuth();
        $bills = Bill::orderByRaw("CASE WHEN is_active = 0 THEN 1 ELSE 0 END, due_date")
            ->get()
            ->map(fn ($b) => $this->format($b));
        return response()->json(['data' => $bills]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->checkAuth();
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount'      => 'required|numeric|min:0.01',
            'frequency'   => 'required|in:one_time,daily,weekly,bi_weekly,monthly,quarterly,semi_annual,annual',
            'due_date'    => 'required|date',
            'category'    => 'nullable|string|max:100',
        ]);

        $bill = Bill::create([...$data, 'user_id' => auth()->id()]);
        return response()->json(['data' => $this->format($bill)], 201);
    }

    public function update(Request $request, Bill $bill): JsonResponse
    {
        $this->checkAuth();
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'amount'      => 'sometimes|numeric|min:0.01',
            'frequency'   => 'sometimes|in:one_time,daily,weekly,bi_weekly,monthly,quarterly,semi_annual,annual',
            'due_date'    => 'sometimes|date',
            'category'    => 'nullable|string|max:100',
            'is_active'   => 'boolean',
        ]);

        $bill->update($data);
        return response()->json(['data' => $this->format($bill->fresh())]);
    }

    public function destroy(Bill $bill): JsonResponse
    {
        $this->checkAuth();
        $bill->delete();
        return response()->json(null, 204);
    }

    public function pay(Request $request, Bill $bill): JsonResponse
    {
        $this->checkAuth();

        if (!$bill->is_active) {
            return response()->json(['message' => 'This bill is no longer active.'], 422);
        }

        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'notes'  => 'nullable|string',
        ]);

        $paidAmount = isset($data['amount']) ? (float) $data['amount'] : (float) $bill->amount;

        DB::transaction(function () use ($bill, $paidAmount, $data) {
            FinancialTransaction::create([
                'type'          => 'expense',
                'amount'        => $paidAmount,
                'description'   => 'Bill: ' . $bill->name,
                'notes'         => $data['notes'] ?? sprintf(
                    '%s · Due %s · %s',
                    ucfirst(str_replace('_', '-', $bill->frequency)),
                    $bill->due_date->format('M d, Y'),
                    $bill->category ?? ''
                ),
                'user_id'       => auth()->id(),
                'transacted_at' => now(),
            ]);

            $next = $bill->nextDueDate();
            if ($next === null) {
                $bill->update(['is_active' => false, 'last_paid_at' => now()]);
            } else {
                $bill->update(['due_date' => $next->toDateString(), 'last_paid_at' => now()]);
            }
        });

        return response()->json(['data' => $this->format($bill->fresh())]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $this->checkAuth();
        $months = min(max((int) ($request->months ?? 3), 1), 12);
        $endDate = Carbon::today()->addMonths($months)->endOfMonth();

        $bills = Bill::where('is_active', true)->get();
        $entries = [];

        foreach ($bills as $bill) {
            $current = Carbon::parse($bill->due_date);

            while ($current->lte($endDate)) {
                $entries[] = [
                    'bill_id'   => $bill->id,
                    'name'      => $bill->name,
                    'category'  => $bill->category,
                    'amount'    => (float) $bill->amount,
                    'due_date'  => $current->toDateString(),
                    'frequency' => $bill->frequency,
                    'status'    => ($current->isPast() && !$current->isToday()) ? 'overdue'
                        : ($current->isToday() ? 'due_today' : 'upcoming'),
                ];

                $next = match ($bill->frequency) {
                    'one_time'    => null,
                    'daily'       => $current->copy()->addDay(),
                    'weekly'      => $current->copy()->addWeek(),
                    'bi_weekly'   => $current->copy()->addWeeks(2),
                    'monthly'     => $current->copy()->addMonth(),
                    'quarterly'   => $current->copy()->addMonths(3),
                    'semi_annual' => $current->copy()->addMonths(6),
                    'annual'      => $current->copy()->addYear(),
                    default       => null,
                };

                if ($next === null) break;
                $current = $next;
            }
        }

        usort($entries, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));

        $byMonth = [];
        foreach ($entries as $entry) {
            $byMonth[substr($entry['due_date'], 0, 7)][] = $entry;
        }

        return response()->json([
            'entries'        => $entries,
            'by_month'       => $byMonth,
            'total_forecast' => round(array_sum(array_column($entries, 'amount')), 2),
            'months'         => $months,
        ]);
    }

    private function format(Bill $b): array
    {
        return [
            'id'           => $b->id,
            'name'         => $b->name,
            'description'  => $b->description,
            'amount'       => (float) $b->amount,
            'frequency'    => $b->frequency,
            'due_date'     => $b->due_date?->toDateString(),
            'category'     => $b->category,
            'is_active'    => (bool) $b->is_active,
            'last_paid_at' => $b->last_paid_at?->toDateTimeString(),
            'status'       => $b->status(),
        ];
    }

    private function checkAuth(): void
    {
        if (!auth()->user()?->hasAnyRole('admin', 'auditor')) abort(403);
    }
}
