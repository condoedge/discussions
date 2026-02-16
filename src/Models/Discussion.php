<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Models\Files\MorphManyFilesTrait;
use Condoedge\Utils\Models\Model;
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
        }
        $db->box = $box;
        $db->save();
    }

    public function markRead()
    {
        $mr = new DiscussionRead();
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

    public function pushSentEvent()
    {
        event(new DiscussionSent(currentTeam()->id, $this));
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
        $isCurrentUser = $this->added_by === auth()->id();

        // Récupérer les utilisateurs qui ont lu le message (sauf l'auteur)
        $readByUsers = $isCurrentUser ? $this->reads()
            ->with('user')
            ->where('added_by', '!=', $this->added_by)
            ->get()
            ->map(fn($read) => $read->user)
            ->filter() : collect();

        $card = _Rows(
            // Name and timestamp (outside bubble)
            _Flex(
                $withImg ? $this->profileImg() : null,
                _Html($this->addedBy->name)->class('text-sm font-medium text-gray-900 ml-2')
            )->class('items-center mb-1'),

            // Message bubble
            _Rows(
                _Html($this->cleanHtml())->class($isCurrentUser
                    ? 'text-white ck ck-content leading-relaxed'
                    : 'text-gray-900 ck ck-content leading-relaxed'
                ),

                !$this->files->count() ? null :
                    _Flex(
                        $this->files->map(function($file){
                            return $file->linkEl()
                                ->href($file->link)
                                ->attr(['download' => $file->name])
                                ->class($isCurrentUser ? 'text-white' : 'text-gray-900');
                        })
                    )->class('mt-2 flex-wrap gap-2'),

                // Timestamp inside bubble avec indicateurs de lecture
                _Flex(
                    _Html($this->created_at->format('H:i'))->class($isCurrentUser
                        ? 'text-xs text-white text-opacity-80'
                        : 'text-xs text-gray-500'
                    ),

                    // Petits ronds de profil des lecteurs (style Messenger)
                    !$isCurrentUser || !$readByUsers->count() ? null :
                        _Flex(
                            $readByUsers->take(3)->map(function($user) {
                                $avatarUrl = $user->avatar_path ?: 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&size=16';
                                return _Img($avatarUrl)->class('w-4 h-4 rounded-full border-2 border-white -ml-1')
                                    ->attr(['title' => $user->name, 'alt' => $user->name]);
                            })
                        )->class('flex -space-x-1 ml-2')
                )->class('mt-1 items-center justify-end')

            )->class($isCurrentUser
                ? 'bg-level1 text-white rounded-2xl rounded-tr-sm px-4 py-3 shadow-sm'
                : 'bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm'
            )->class($this->read ? '' : 'ring-2 ring-level3 ring-opacity-30')

        )->class('message-bubble-container group');

        if($this->notification)
            $this->notification->markSeen();

        return $card;
    }

    public function discussionText()
    {
        $unreadCue = '<span class="text-level1 opacity-60 text-xs">('.__('discussions.new').')</span> ';

        return _Rows(
            _UserDate(
                ($this->read ? '' : $unreadCue).$this->addedBy->name,
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
        return _ProfileImg($this->addedBy)->class('mr-2 mt-2 shrink-0');
    }

    public function cleanHtml()
    {
        // Remove empty paragraphs with &nbsp; or just whitespace
        $html = $this->html;
        $html = preg_replace('/<p>(&nbsp;|\s|<br\s*\/?>)*<\/p>/i', '', $html);
        return trim($html);
    }
}
