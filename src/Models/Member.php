<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Models\Model;
use Kompo\Auth\Facades\UserModel;

class Member extends Model
{
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function user()
    {
        return $this->belongsTo(UserModel::getClass());
    }
}
