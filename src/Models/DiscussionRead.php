<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Models\Model;

class DiscussionRead extends Model
{
    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'added_by');
    }
}
