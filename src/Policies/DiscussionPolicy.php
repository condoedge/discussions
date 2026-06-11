<?php

namespace Kompo\Discussions\Policies;

use Kompo\Discussions\Models\Discussion;

class DiscussionPolicy
{
    public function view($user, Discussion $discussion)
    {
        return $user->can('view', $discussion->channel);
    }

    public function create($user)
    {
        // Channel-level access is checked where the target channel is known
        return true;
    }

    public function update($user, Discussion $discussion)
    {
        return (int) $discussion->added_by === (int) $user->id;
    }

    public function delete($user, Discussion $discussion)
    {
        return (int) $discussion->added_by === (int) $user->id
            || $user->can('delete', $discussion->channel);
    }
}
