<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Models\Model;
use Kompo\Auth\Facades\UserModel;

class DiscussionRead extends Model
{
    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user()
    {
        return $this->belongsTo(UserModel::getClass(), 'added_by');
    }
}
