<?php

namespace Kompo\Discussions\Models;

use Kompo\Discussions\Models\Traits\HasManyDiscussions;
use App\Models\User;
use Condoedge\Utils\Models\Model;
use Condoedge\Utils\Models\Traits\BelongsToTeamTrait;

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

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'members');
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
        return static::with('lastDiscussion.addedBy', 'lastDiscussion.read');
    }

    public static function queryForUser()
    {
        $userChannelsIds = self::where('added_by', auth()->id())
            ->orWhereHas('users', function ($query) {
                $query->where('users.id', auth()->id());
            })->pluck('channels.id');

        return Channel::whereIn('channels.id', $userChannelsIds)
            ->withCount('subjects')
            ->with('lastDiscussion.addedBy', 'lastDiscussion.read')
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
