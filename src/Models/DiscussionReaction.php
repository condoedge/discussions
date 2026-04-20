<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Models\Model;

class DiscussionReaction extends Model
{
    protected $fillable = ['discussion_id', 'user_id', 'emoji'];

    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
