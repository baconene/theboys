<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintJob;
use App\Models\PrintServiceSetting;
use Pusher\PushNotifications\PushNotifications;
use Pusher\Pusher;

class PrintJobService
{
    public function queueForNewOrder(Order $order): PrintJob
    {
        return $this->createAndDeliver($order, 'new_order', null);
    }

    public function queueForStatusChange(Order $order, string $newStatus): PrintJob
    {
        return $this->createAndDeliver($order, 'status_change', $newStatus);
    }

    private function createAndDeliver(Order $order, string $trigger, ?string $triggerStatus): PrintJob
    {
        $order->loadMissing(['items.product', 'user', 'queueNumber', 'payments.tender']);

        $receiptPayload = $this->buildAndroidReceipt($order);

        $job = PrintJob::create([
            'order_id'       => $order->id,
            'trigger'        => $trigger,
            'trigger_status' => $triggerStatus,
            'status'         => 'pending',
            'receipt_data'   => $receiptPayload,
        ]);

        $delivered = false;

        // ── Path 1: Pusher Channels (WebSocket) — direct SDK, same as testChannels endpoint ──
        // Using Pusher SDK directly bypasses BROADCAST_CONNECTION config entirely,
        // so it works regardless of whether config:clear has been run.
        try {
            $key    = config('broadcasting.connections.pusher.key');
            $secret = config('broadcasting.connections.pusher.secret');
            $appId  = config('broadcasting.connections.pusher.app_id');

            if ($key && $secret && $appId) {
                $pusher = new Pusher($key, $secret, $appId, [
                    'cluster' => config('broadcasting.connections.pusher.options.cluster', 'ap1'),
                    'useTLS'  => true,
                ]);

                $pusher->triggerBatch([
                    ['channel' => 'orders', 'name' => 'App\\Events\\NewReceiptEvent', 'data' => ['receipt' => $receiptPayload]],
                    ['channel' => 'orders', 'name' => 'print',                        'data' => ['receipt' => $receiptPayload]],
                ]);

                $delivered = true;
            }
        } catch (\Throwable) {
            // Channels not configured or unreachable — fall through to Beams
        }

        // ── Path 2: Pusher Beams (FCM push) — background wake-up ────────────────
        // Note: FCM data values are strings only, so we can't embed nested JSON here.
        // We send only a notification (title + body) + print_job_id so the Android
        // can fetch the receipt from the API if needed.
        try {
            $instanceId = config('broadcasting.beams.instance_id');
            $secretKey  = config('broadcasting.beams.secret_key');

            if ($instanceId && $secretKey) {
                $beams = new PushNotifications([
                    'instanceId' => $instanceId,
                    'secretKey'  => $secretKey,
                ]);

                $beams->publishToInterests(
                    ['print-jobs'],
                    [
                        'fcm' => [
                            'notification' => [
                                'title' => $this->notificationTitle($trigger, $triggerStatus, $order->id),
                                'body'  => $this->notificationBody($order),
                            ],
                            'data' => [
                                'print_job_id' => (string) $job->id,
                            ],
                        ],
                    ]
                );

                $delivered = true;
            }
        } catch (\Throwable) {
            // Non-critical
        }

        $job->update([
            'status'   => $delivered ? 'sent' : 'failed',
            'sent_at'  => $delivered ? now() : null,
            'attempts' => 1,
            'failed_reason' => $delivered ? null : 'No delivery channel configured (no Pusher Channels key and no Beams credentials)',
        ]);

        return $job;
    }

    private function buildAndroidReceipt(Order $order): array
    {
        $settings = PrintServiceSetting::getSetting();

        return [
            'store' => [
                'name'    => $settings->print_store_name ?: config('app.name'),
                'address' => $settings->print_store_address,
                'phone'   => $settings->print_store_phone,
                'footer'  => $settings->print_footer ?: 'Thank you!',
            ],
            'receipt' => [
                'number'  => (string) $order->id,
                'date'    => $order->created_at?->setTimezone('Asia/Manila')->format('M d, Y h:i A'),
                'cashier' => $order->user?->name,
            ],
            'items' => $order->items->map(fn ($item) => [
                'name'  => $item->product?->name ?? 'Unknown',
                'qty'   => (float) $item->quantity,
                'price' => (float) $item->unit_price,
                'total' => (float) $item->subtotal,
            ])->values()->all(),
            'totals' => [
                'subtotal' => (float) $order->subtotal,
                'tax'      => (float) $order->tax_amount,
                'discount' => (float) $order->discount_amount,
                'total'    => (float) $order->total_amount,
            ],
            'payment' => null,
        ];
    }

    private function notificationTitle(string $trigger, ?string $status, int $orderId): string
    {
        return $trigger === 'new_order'
            ? "New Order #$orderId — Print Required"
            : "Order #$orderId — " . ucfirst($status ?? 'Updated');
    }

    private function notificationBody(Order $order): string
    {
        $itemCount = $order->items->count();
        $total     = number_format((float) $order->total_amount, 2);
        return "{$itemCount} item(s) · ₱{$total}"
            . ($order->table_number ? " · Table {$order->table_number}" : '');
    }
}
