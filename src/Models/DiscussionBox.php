<?php

namespace Kompo\Discussions\Models;

use Kompo\Auth\Models\Model;

class DiscussionBox extends Model
{    
    public function discussion()
    {
        return $this->belongsTo(Discussion::class);
    }

    /* SCOPES */
    public function scopeArchive($query)
    {
        $query->where('box', 1);
    }
    
    public function scopeTrash($query)
    {
        $query->where('box', 2);
    }
    
    public function scopeNotTrash($query)
    {
        $query->where('box', '<>', 2);
    }
}
