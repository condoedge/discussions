<?php

namespace Kompo\Discussions\Models;

use Kompo\Auth\Models\Files\MorphManyFilesTrait;
use Kompo\Auth\Models\Model;
use Kompo\Auth\Models\Traits\BelongsToUserTrait;
use Kompo\Discussions\Events\DiscussionSent;
use Kompo\Discussions\Models\Traits\HasManyDiscussions;

class Discussion extends Model
{
    use HasManyDiscussions,
        MorphManyFilesTrait;

    /* RELATIONS */
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function discussion()
    {
        return $this->belongsTo(self::class);
    }

    public function reads()
    {
        return $this->hasMany(DiscussionRead::class);
    }

    public function read()
    {
    	return $this->hasOne(DiscussionRead::class)->forAuthUser();
    }

    public function boxes()
    {
        return $this->hasMany(DiscussionBox::class);
    }

    public function box()
    {
        return $this->hasOne(DiscussionBox::class)->forAuthUser();
    }

    public function isArchived()
    {
        return $this->box()->archive();
    }

    public function isTrashed()
    {
        return $this->box()->trash();
    }

    /* CALCULATED FIELDS */
    // public function getAllMentionnedUsers()
    // {
    //     $notifiedUsers = Notification::whereIn('about_id', $this->queryRelated()->pluck('id'))
    //                         ->where('about_type', 'discussion')
    //                         ->pluck('user_id');

    //     $discussionUsers = $this->queryRelated()->pluck('user_id');

    //     $parentDiscussion = $this->discussion ?: $this;

    //     return $notifiedUsers->concat($discussionUsers)->concat([$parentDiscussion->user_id])->unique();
    // }

    public function queryRelated()
    {
        return $this->discussion_id ?
                static::where('discussion_id', $this->discussion_id)->orWhere('id', $this->discussion_id) :
                static::where('id', $this->id)->orWhere('discussion_id', $this->id);
    }

    /* ACTIONS */
    // public function notify($userId)
    // {
    //     if (!$userId) {
    //         return;
    //     }

    //     if(!$this->channel->getAllowedUserIds()->contains($userId)) {
    //         return;
    //     }

    //     if(Notification::userHasUnseenNotifications($userId, $this->queryRelated()->pluck('id'), 'discussion')){
    //         return;
    //     }

    //     Notification::notify($this, $userId);

    // }

    public function updateBox($box)
    {
        if(!($db = $this->box)){
            $db = new DiscussionBox();
            $db->discussion_id = $this->id;
            $db->setUserId();
        }
        $db->box = $box;
        $db->save();
    }

    public function markRead()
    {
        $mr = new DiscussionRead();
        $mr->setUserId();
        $mr->discussion_id = $this->id;
        $mr->read_at = now();
        $mr->save();
    }

    public function setSummaryFrom($text)
    {
        $this->summary = safeTruncate($text);
    }

    public static function pusherBroadcast()
    {
        broadcast(new DiscussionSent(currentTeam()->id))->toOthers();
    }

    public static function pusherRefresh()
    {
        return [
            'discussion.'.currentTeam()->id => DiscussionSent::class
        ];
    }

    public function delete()
    {
        $this->discussions->each->delete(); //child discussions

        $this->deleteFiles();
        // $this->deleteNotifications();
        $this->reads->each->delete();

        parent::delete();
    }

    /* ELEMENTS */
    public function cardWithActions($withImg = true)
    {
        $card = _Flex(
            $withImg ? $this->profileImg() : null,
            $this->discussionText()->class('pb-2 flex-auto')
        )->alignStart()
        ->class('pb-2 px-4 bg-white');

        if($this->notification)
            $this->notification->markSeen();

        return $card;
    }

    public function discussionText()
    {
        $unreadCue = '<span class="text-level1 opacity-60 text-xs">('.__('New').')</span> ';

        return _Rows(
            _UserDate(
                ($this->read ? '' : $unreadCue).$this->user->name,
                $this->created_at
            )->class('mb-2'),
            _Html($this->html)
                ->class('text-level1 ck ck-content'),

            !$this->files->count() ? null :

                _Flex(
                    $this->files->map(function($file){
                        return $file->linkEl()
                            ->href($file->link)
                            ->attr(['download' => $file->name]);
                    })
                )->class('mt-2 flex-wrap'),
        )->class('flex-auto pb-2'.($this->read ? '' : ' border-l-4 border-level3 border-opacity-50 pl-4'));
    }

    public function profileImg()
    {
        return _ProfileImg($this->user)->class('mr-2 mt-2 shrink-0');
    }
}
