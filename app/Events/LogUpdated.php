<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class LogUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public $logs;

    public function __construct($logs)
    {
        $this->logs = $logs;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('log-channel');
    }

    public function broadcastAs(): string
    {
        return 'log.updated';
    }
}
