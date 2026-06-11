<?php

namespace Kompo\Discussions\Models;

use Condoedge\Utils\Kompo\Chat\ChatBubbleRenderer;
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

    /* SCOPES */
    public function scopeTopLevel($query)
    {
        $query->whereNull('discussion_id');
    }

    public function scopeInBox($query, $box)
    {
        $box ?
            $query->whereHas('box', fn($q) => $q->where('box', $box)) :
            $query->doesntHave('box');
    }

    /* CALCULATED FIELDS */
    public function isOwn()
    {
        return (int) $this->added_by === (int) auth()->id();
    }

    public function readers()
    {
        return $this->reads->where('added_by', '!=', $this->added_by)
            ->map(fn($read) => $read->user)
            ->filter()
            ->unique('id')
            ->values();
    }

    /* ACTIONS */
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
        if ($this->read()->exists()) {
            return;
        }

        $mr = new DiscussionRead();
        $mr->discussion_id = $this->id;
        $mr->read_at = now();
        $mr->save();
    }

    public function setSummaryFrom($text)
    {
        $this->summary = safeTruncate($text);
    }

    public function reopenForAllUsers()
    {
        // A new reply pulls the thread out of every member's archive/trash box
        $this->boxes()->delete();
    }

    public function broadcastSent()
    {
        broadcast(new DiscussionSent($this->channel->team_id, $this))->toOthers();
    }

    public static function pusherRefresh($teamIds = null)
    {
        $teamIds = collect($teamIds ?: [currentTeam()->id]);

        // The leading dot tells Laravel Echo to use the name verbatim instead of
        // prepending its default "App.Events" namespace; it must match broadcastAs()
        return $teamIds->filter()->unique()->mapWithKeys(fn($teamId) => [
            'discussion.'.$teamId => ['.'.DiscussionSent::BROADCAST_NAME],
        ])->toArray();
    }

    public function delete()
    {
        $this->discussions->each->delete(); //child discussions

        $this->deleteFiles();
        $this->reads->each->delete();
        $this->boxes->each->delete();

        parent::delete();
    }

    /* ELEMENTS */
    public function cardWithActions($withImg = true)
    {
        $isOwn = $this->isOwn();
        $renderer = new ChatBubbleRenderer();

        // Theming note: the kit colors the bubble through CSS custom properties
        // (discussions overrides --chat-bubble-own-bg to the brand level1 green in scss)
        $content = [
            _Html($this->cleanHtml())->class('ck ck-content leading-relaxed'),
            $this->attachmentLinks($isOwn),
        ];

        $authorName = $this->addedBy->name;
        $avatar = $withImg ? _ProfileImg($this->addedBy) : null;
        $timestamp = $this->created_at->format('H:i');
        $footer = $isOwn ? $this->readReceiptAvatars() : null;
        $unread = !$this->read;
        $animate = !$this->created_at || $this->created_at->diffInSeconds(now()) < 5;

        return $isOwn
            ? $renderer->ownBubble($content, $authorName, $avatar, $timestamp, $footer, $unread, false, $animate)
            : $renderer->otherBubble($content, $authorName, $avatar, $timestamp, $footer, $unread, false, $animate);
    }

    protected function attachmentLinks($isOwn)
    {
        if (!$this->files->count()) {
            return null;
        }

        return _Flex(
            $this->files->map(function($file) use ($isOwn) {
                return $file->linkEl()
                    ->href($file->link)
                    ->attr(['download' => $file->name])
                    ->class($isOwn ? 'text-white' : 'text-gray-900');
            })
        )->class('mt-2 flex-wrap gap-2');
    }

    // Small reader avatars (Messenger style), rendered beside the timestamp
    protected function readReceiptAvatars()
    {
        $readByUsers = $this->readers();

        if (!$readByUsers->count()) {
            return null;
        }

        return _Flex(
            $readByUsers->take(3)->map(function($user) {
                return _Img(discussionsAvatarUrl($user, 16))
                    ->class('w-4 h-4 rounded-full border-2 border-white -ml-1')
                    ->attr(['title' => $user->name, 'alt' => $user->name]);
            })
        )->class('flex -space-x-1');
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
