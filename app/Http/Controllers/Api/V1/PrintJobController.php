<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ReceiptQueued;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PrintJob;
use App\Services\PrintJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintJobController extends Controller
{
    public function __construct(private PrintJobService $printJobService) {}

    // List jobs — Android uses this to re-sync pending jobs after reconnect
    public function index(Request $request): JsonResponse
    {
        $jobs = PrintJob::with('order')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->order_id, fn ($q) => $q->where('order_id', $request->order_id))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($job) => [
                'id'             => $job->id,
                'order_id'       => $job->order_id,
                'trigger'        => $job->trigger,
                'trigger_status' => $job->trigger_status,
                'status'         => $job->status,
                'attempts'       => $job->attempts,
                'created_at'     => $job->created_at,
                'printed_at'     => $job->printed_at,
                'receipt'        => $job->receipt_data,
            ]);

        return response()->json($jobs);
    }

    // Manually trigger a print job for an order (e.g. Reprint button)
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $order = Order::with(['items.product', 'user', 'queueNumber', 'payments.tender'])
            ->findOrFail($request->order_id);

        $job = $this->printJobService->queueForNewOrder($order);

        return response()->json([
            'id'         => $job->id,
            'status'     => $job->status,
            'created_at' => $job->created_at,
        ], 201);
    }

    // Android calls this after printing to acknowledge success or failure
    public function acknowledge(Request $request, PrintJob $printJob): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:printed,failed',
            'reason' => 'nullable|string|max:255',
        ]);

        $printJob->update([
            'status'        => $data['status'],
            'failed_reason' => $data['status'] === 'failed' ? ($data['reason'] ?? null) : null,
            'printed_at'    => $data['status'] === 'printed' ? now() : null,
        ]);

        return response()->json(['id' => $printJob->id, 'status' => $printJob->status]);
    }

    // Retry a failed job — rebroadcast the Pusher event
    public function retry(PrintJob $printJob): JsonResponse
    {
        if (! in_array($printJob->status, ['failed', 'sent'])) {
            return response()->json(['message' => 'Only failed or stuck jobs can be retried.'], 422);
        }

        $printJob->increment('attempts');
        $printJob->update(['status' => 'sent', 'sent_at' => now(), 'failed_reason' => null]);

        broadcast(new ReceiptQueued($printJob));

        return response()->json(['id' => $printJob->id, 'status' => $printJob->status]);
    }
}
