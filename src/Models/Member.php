<?php

namespace Kompo\Discussions\Models;

use Kompo\Auth\Models\Model;

class Member extends Model
{
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
