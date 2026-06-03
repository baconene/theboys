<?php

namespace App\Services;

use App\Events\ReceiptQueued;
use App\Models\Order;
use App\Models\PrintJob;

class PrintJobService
{
    public function queueForNewOrder(Order $order): PrintJob
    {
        return $this->createAndBroadcast($order, 'new_order', null);
    }

    public function queueForStatusChange(Order $order, string $newStatus): PrintJob
    {
        return $this->createAndBroadcast($order, 'status_change', $newStatus);
    }

    private function createAndBroadcast(Order $order, string $trigger, ?string $triggerStatus): PrintJob
    {
        $order->loadMissing(['items.product', 'user', 'queueNumber', 'payments.tender']);

        $job = PrintJob::create([
            'order_id'       => $order->id,
            'trigger'        => $trigger,
            'trigger_status' => $triggerStatus,
            'status'         => 'pending',
            'receipt_data'   => $this->buildReceiptData($order),
        ]);

        broadcast(new ReceiptQueued($job))->toOthers();

        $job->update(['status' => 'sent', 'sent_at' => now(), 'attempts' => 1]);

        return $job;
    }

    private function buildReceiptData(Order $order): array
    {
        return [
            'id'               => $order->id,
            'queue_number'     => $order->queueNumber?->number,
            'order_type'       => $order->order_type,
            'table_number'     => $order->table_number,
            'status'           => $order->status,
            'payment_status'   => $order->payment_status,
            'customer_name'    => $order->customer_name,
            'customer_contact' => $order->customer_contact,
            'customer_address' => $order->customer_address,
            'notes'            => $order->notes,
            'subtotal'         => (float) $order->subtotal,
            'discount_amount'  => (float) $order->discount_amount,
            'tax_amount'       => (float) $order->tax_amount,
            'total_amount'     => (float) $order->total_amount,
            'items'            => $order->items->map(fn ($item) => [
                'name'       => $item->product?->name ?? 'Unknown',
                'quantity'   => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal'   => (float) $item->subtotal,
            ])->values()->all(),
            'cashier'          => $order->user?->name,
            'created_at'       => $order->created_at?->toISOString(),
        ];
    }
}
