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

    protected $casts = [
        'is_private' => 'boolean',
    ];

    public const DEFAULT_ICON = 'messages';
    public const DEFAULT_COLOR = 'greenmain';

    public const COLOR_HEX = [
        'greenmain' => '#0a6e4e',
        'blue'      => '#2563eb',
        'purple'    => '#8b5cf6',
        'red'       => '#ef4444',
        'orange'    => '#f97316',
        'yellow'    => '#eab308',
        'teal'      => '#14b8a6',
        'pink'      => '#ec4899',
    ];

    public function getIconName(): string
    {
        return $this->icon ?: self::DEFAULT_ICON;
    }

    public function getColorHex(): string
    {
        return self::COLOR_HEX[$this->color ?? self::DEFAULT_COLOR] ?? self::COLOR_HEX[self::DEFAULT_COLOR];
    }

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

    public static function queryForUser($userId = null)
    {
        $userId = $userId ?: auth()->id();

        $userChannelsIds = self::where('added_by', $userId)
            ->orWhereHas('users', function ($query) use ($userId) {
                $query->where('users.id', $userId);
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
