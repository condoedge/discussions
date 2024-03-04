<?php

namespace Kompo\Discussions\Policies;

use Kompo\Discussions\Models\Channel;

class ChannelPolicy
{
    public function view(\App\Models\User $user, Channel $channel)
    {
        return $channel->forAuthUser($user->id)->exists();
    }
}