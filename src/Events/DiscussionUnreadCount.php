<?php

namespace Kompo\Discussions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Authoritative unread-message count, broadcast to the reader's OWN channel
 * after they read messages — keeps the navbar badge (all their tabs) in sync.
 */
class DiscussionUnreadCount implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const BROADCAST_NAME = 'DiscussionUnreadCount';

    protected $userId;
    protected $count;

    public function __construct($userId, int $count)
    {
        $this->userId = $userId;
        $this->count = $count;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('discussion-user.'.$this->userId);
    }

    public function broadcastAs()
    {
        return static::BROADCAST_NAME;
    }

    public function broadcastWith()
    {
        return ['count' => $this->count];
    }
}
