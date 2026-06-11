<?php

namespace Kompo\Discussions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Kompo\Discussions\Models\Channel;

/**
 * Domain event: a user was added to a discussion channel. The host app listens
 * and notifies however it wants (per-message notifications are NOT dispatched
 * anymore — live toasts and unread badges cover those).
 */
class MemberAddedToChannel
{
    use Dispatchable, SerializesModels;

    public Channel $channel;
    public $userId;

    public function __construct(Channel $channel, $userId)
    {
        $this->channel = $channel;
        $this->userId = $userId;
    }
}
