<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReceiptQueued implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrintJob $printJob) {}

    public function broadcastOn(): array
    {
        return [new Channel('print-jobs')];
    }

    public function broadcastAs(): string
    {
        return 'receipt.queued';
    }

    public function broadcastWith(): array
    {
        return [
            'print_job_id'   => $this->printJob->id,
            'trigger'        => $this->printJob->trigger,
            'trigger_status' => $this->printJob->trigger_status,
            'receipt'        => $this->printJob->receipt_data,
        ];
    }
}
