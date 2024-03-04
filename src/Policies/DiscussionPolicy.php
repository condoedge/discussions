<?php

namespace Kompo\Discussions\Policies;

class DiscussionPolicy
{
    public function viewAny($user)
    {
        return true;
    }

    public function view($user, $discussion)
    {
        return true;
    }

    public function create($user)
    {
        return true;
    }

    public function update($user, $discussion)
    {
        return true;
    }

    public function delete($user, $discussion)
    {
        return true;
    }

    public function restore($user, $discussion)
    {
        return true;
    }

    public function forceDelete($user, $discussion)
    {
        return true;
    }
}