<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Models\Model;

class DiscussionMention extends Model
{
    protected $fillable = ['discussion_id', 'user_id', 'seen_at'];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopeUnseen($query)
    {
        return $query->whereNull('seen_at');
    }
}
