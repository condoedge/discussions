<?php

namespace Kompo\Discussions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user reads discussions (batched: one event per team per request),
 * so senders' panels refresh their read receipts live.
 */
class DiscussionsRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const BROADCAST_NAME = 'DiscussionsRead';

    protected $teamId;

    public function __construct($teamId)
    {
        $this->teamId = $teamId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('discussion.'.$this->teamId);
    }

    public function broadcastAs()
    {
        return static::BROADCAST_NAME;
    }
}
