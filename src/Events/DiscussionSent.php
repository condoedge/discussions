<?php

namespace Kompo\Discussions\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kompo\Discussions\Models\Discussion;

class DiscussionSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Broadcast name shared with the front end: Kompo serializes pusherRefresh()
     * with class_basename(), and Laravel Echo prepends its "App.Events" namespace
     * unless the listened name starts with a dot. Keeping a custom name here
     * (instead of the full class name) is what lets both sides match.
     */
    public const BROADCAST_NAME = 'DiscussionSent';

    protected $teamId;
    protected ?Discussion $discussion;

    public function __construct($teamId, ?Discussion $discussion = null)
    {
        $this->teamId = $teamId;
        $this->discussion = $discussion;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('discussion.'.$this->teamId);
    }

    public function broadcastAs()
    {
        return static::BROADCAST_NAME;
    }

    public function getDiscussion()
    {
        return $this->discussion;
    }
}
