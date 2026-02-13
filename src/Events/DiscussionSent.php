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

    protected $teamId;
    protected ?Discussion $discussion;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($teamId, ?Discussion $discussion = null)
    {
        $this->teamId = $teamId;
        $this->discussion = $discussion;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('discussion.'.$this->teamId);
    }

    public function getDiscussion()
    {
        return $this->discussion;
    }
}
