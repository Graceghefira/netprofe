<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class LeaseFetched implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $leases;

    public function __construct($leases)
    {
        $this->leases = $leases;
    }

    public function broadcastOn()
    {
        return new Channel('leases-channel');
    }

    public function broadcastWith()
    {
        return [
            'leases' => $this->leases,
        ];
    }
}
