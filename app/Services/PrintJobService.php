<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintJob;
use Pusher\PushNotifications\PushNotifications;

class PrintJobService
{
    private function beamsClient(): PushNotifications
    {
        return new PushNotifications([
            'instanceId' => config('broadcasting.beams.instance_id'),
            'secretKey'  => config('broadcasting.beams.secret_key'),
        ]);
    }

    public function queueForNewOrder(Order $order): PrintJob
    {
        return $this->createAndNotify($order, 'new_order', null);
    }

    public function queueForStatusChange(Order $order, string $newStatus): PrintJob
    {
        return $this->createAndNotify($order, 'status_change', $newStatus);
    }

    private function createAndNotify(Order $order, string $trigger, ?string $triggerStatus): PrintJob
    {
        $order->loadMissing(['items.product', 'user', 'queueNumber', 'payments.tender']);

        $job = PrintJob::create([
            'order_id'       => $order->id,
            'trigger'        => $trigger,
            'trigger_status' => $triggerStatus,
            'status'         => 'pending',
            'receipt_data'   => $this->buildReceiptData($order),
        ]);

        try {
            $this->beamsClient()->publishToInterests(
                ['print-jobs'],
                [
                    'fcm' => [
                        'notification' => [
                            'title' => $this->notificationTitle($trigger, $triggerStatus, $order->id),
                            'body'  => $this->notificationBody($order),
                        ],
                        'data' => [
                            'print_job_id'   => (string) $job->id,
                            'trigger'        => $trigger,
                            'trigger_status' => $triggerStatus ?? '',
                            'receipt'        => json_encode($job->receipt_data),
                        ],
                    ],
                ]
            );

            $job->update(['status' => 'sent', 'sent_at' => now(), 'attempts' => 1]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'failed_reason' => $e->getMessage()]);
        }

        return $job;
    }

    private function notificationTitle(string $trigger, ?string $status, int $orderId): string
    {
        if ($trigger === 'new_order') return "New Order #$orderId";
        return "Order #$orderId — " . ucfirst($status ?? 'Updated');
    }

    private function notificationBody(Order $order): string
    {
        $itemCount = $order->items->count();
        $total     = number_format((float) $order->total_amount, 2);
        return "{$itemCount} item(s) · ₱{$total}" . ($order->table_number ? " · Table {$order->table_number}" : '');
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
