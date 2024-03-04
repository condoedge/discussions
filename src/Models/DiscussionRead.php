<?php

namespace Kompo\Discussions\Models;

use Kompo\Auth\Models\Model;

class DiscussionRead extends Model
{
    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }
}
