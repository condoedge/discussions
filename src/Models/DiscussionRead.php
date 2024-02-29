<?php

namespace Kompo\Discussions\Models;

use Kompo\Auth\Models\Model;
use Kompo\Auth\Models\Traits\BelongsToUserTrait;

class DiscussionRead extends Model
{
    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }
}
