<?php

namespace Kompo\Discussions\Models;

use Kompo\Auth\Models\Model;
use Kompo\Auth\Models\Teams\BelongsToTeamTrait;
use Kompo\Auth\Models\Traits\BelongsToUserTrait;
use Kompo\Discussions\Models\Traits\HasManyDiscussions;
use App\Models\User;

class Channel extends Model
{
    use HasManyDiscussions,
        BelongsToTeamTrait;

    public function subjects()
    {
        return $this->discussions()
            ->whereNotNull('subject')
            ->whereNull('discussion_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /* ATTRIBUTES */
    public function getDisplayAttribute()
    {
        return $this->name;
    }

    /* CALCULATED FIELDS */
    public function getAllowedUserIds()
    {
        return $this->users()->pluck('users.id')->concat([$this->user_id]);
    }

    /* QUERIES */
    public static function withBasicInfo()
    {
        return static::with('lastDiscussion.user', 'lastDiscussion.read');
    }

    public static function queryForUser()
    {
        $userChannelsIds = self::where('added_by', auth()->id())
            ->orWhereHas('users', function ($query) {
                $query->where('users.id', auth()->id());
            });

        return Channel::whereIn('channels.id', $userChannelsIds)
            ->withCount('subjects')
            ->with('lastDiscussion.user', 'lastDiscussion.read')
            ->ordered();
    }

    /* SCOPES */
    public function scopeOrdered($query)
    {
        $query->withMax('lastDiscussion', 'created_at')
            ->orderByDesc('last_discussion_max_created_at');
    }

    /* ACTIONS */
    public function delete()
    {
        $this->discussions->each->delete();
        $this->members->each->delete();

        parent::delete();
    }


}
