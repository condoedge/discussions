<?php

namespace Kompo\Discussions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kompo\Discussions\Models\Discussion;

/**
 * App-wide "you received a message" toast. Broadcast on each PARTICIPANT's own
 * private channel (discussion-user.{id}) instead of the team channel, so the
 * payload (author, channel name, summary) never reaches team members who are
 * not in the discussion channel. The author is excluded server-side.
 */
class DiscussionNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const BROADCAST_NAME = 'DiscussionNotification';

    protected $recipientIds;
    protected $payload;

    public function __construct(Discussion $discussion)
    {
        $this->recipientIds = $discussion->channel->participants()
            ->pluck('id')
            ->reject(fn($id) => (int) $id === (int) $discussion->added_by)
            ->values()
            ->all();

        $this->payload = [
            'authorName' => $discussion->addedBy->name,
            'channelName' => $discussion->channel->display,
            'channelId' => $discussion->channel_id,
            'summary' => safeTruncate($discussion->html, 80),
        ];
    }

    public function broadcastOn()
    {
        return collect($this->recipientIds)
            ->map(fn($id) => new PrivateChannel('discussion-user.'.$id))
            ->all();
    }

    public function broadcastAs()
    {
        return static::BROADCAST_NAME;
    }

    public function broadcastWith()
    {
        return $this->payload;
    }
}
