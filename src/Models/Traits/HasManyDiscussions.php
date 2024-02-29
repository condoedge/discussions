<?php

namespace Kompo\Discussions\Models\Traits;

use Kompo\Discussions\Models\Discussion;

trait HasManyDiscussions
{
    public function discussions()
    {
        return $this->hasMany(Discussion::class);
    }

    public function lastDiscussion()
    {
        return $this->hasOne(Discussion::class)->orderBy('created_at', 'DESC');
    }

    /* CALCULATED FIELDS */
    public function hasUnreadDiscussions()
    {
    	return !($this->read ?? true) || ($this->lastDiscussion ? !$this->lastDiscussion->read : false);
    }
}