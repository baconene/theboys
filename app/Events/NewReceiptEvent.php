<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewReceiptEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $receiptPayload) {}

    public function broadcastOn(): array
    {
        // Android subscribes to 'orders' channel
        return [new Channel('orders')];
    }

    public function broadcastWith(): array
    {
        // Android's handleIncomingPrint checks for 'receipt' key in the event data,
        // then calls JsonObject.toString() on it — works because it's a JsonObject (not a string).
        return ['receipt' => $this->receiptPayload];
    }

    // No broadcastAs() — default is "App\Events\NewReceiptEvent" which is what the Android binds to.
}
