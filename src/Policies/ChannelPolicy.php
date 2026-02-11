<?php

namespace Kompo\Discussions\Policies;

use Kompo\Discussions\Models\Channel;

class ChannelPolicy
{
    public function view(\App\Models\User $user, Channel $channel)
    {
        return Channel::queryForUser($user->id)->where('channels.id', $channel->id)->exists();
    }
}