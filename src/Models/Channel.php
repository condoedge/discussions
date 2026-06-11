<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Models\Model;
use Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Facades\UserModel;
use Kompo\Discussions\Models\Traits\HasManyDiscussions;

class Channel extends Model
{
    use HasManyDiscussions,
        BelongsToTeamTrait;

    /* RELATIONS */
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
        return $this->belongsToMany(UserModel::getClass(), 'members');
    }

    /* ATTRIBUTES */
    public function getDisplayAttribute()
    {
        return $this->name;
    }

    /* CALCULATED FIELDS */
    public function participants()
    {
        return collect([$this->addedBy])
            ->concat($this->users)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function hasParticipant($userId)
    {
        return (int) $this->added_by === (int) $userId
            || $this->users()->where('users.id', $userId)->exists();
    }

    /* SCOPES */
    public function scopeForUser($query, $userId = null)
    {
        $userId = $userId ?: auth()->id();

        $query->where(fn($q) => $q->where('added_by', $userId)
            ->orWhereHas('users', fn($q2) => $q2->where('users.id', $userId)));
    }

    public function scopeOrdered($query)
    {
        $query->withMax('lastDiscussion', 'created_at')
            ->orderByDesc('last_discussion_max_created_at');
    }

    public function scopeWithUnreadCount($query, $userId = null)
    {
        $userId = $userId ?: auth()->id();

        $query->withCount(['discussions as unread_count' => fn($q) => $q
            ->where('added_by', '!=', $userId)
            ->whereDoesntHave('reads', fn($r) => $r->where('added_by', $userId))]);
    }

    /* QUERIES */
    public static function queryForUser($userId = null)
    {
        return static::forUser($userId)
            ->withCount('subjects')
            ->with('lastDiscussion.addedBy', 'lastDiscussion.read')
            ->ordered();
    }

    /* ACTIONS */
    public function deletable()
    {
        // Kompo's delete handler checks deletable() before the policy; keep both aligned
        // (the inherited BelongsToTeamTrait version allows any same-team user)
        return auth()->user() && auth()->user()->can('delete', $this);
    }

    public function delete()
    {
        DB::transaction(function () {
            $this->discussions->each->delete();
            $this->members->each->delete();

            parent::delete();
        });
    }
}
