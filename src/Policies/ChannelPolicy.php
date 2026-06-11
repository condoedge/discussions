<?php

namespace Kompo\Discussions\Policies;

use Kompo\Discussions\Models\Channel;

class ChannelPolicy
{
    public function view($user, Channel $channel)
    {
        return $channel->hasParticipant($user->id);
    }

    public function update($user, Channel $channel)
    {
        return $channel->hasParticipant($user->id);
    }

    public function delete($user, Channel $channel)
    {
        return (int) $channel->added_by === (int) $user->id;
    }
}
