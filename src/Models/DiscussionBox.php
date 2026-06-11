<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Models\Model;

class DiscussionBox extends Model
{
    public const BOX_ARCHIVE = 1;
    public const BOX_TRASH = 2;

    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    /* SCOPES */
    public function scopeArchive($query)
    {
        $query->where('box', static::BOX_ARCHIVE);
    }

    public function scopeTrash($query)
    {
        $query->where('box', static::BOX_TRASH);
    }
}
