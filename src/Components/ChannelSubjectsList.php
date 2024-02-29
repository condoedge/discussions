<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;
use Kompo\Query;

class ChannelSubjectsList extends Query
{
    use DiscussionBoxTrait;

	public $perPage = 10;

    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';
    public $itemsWrapperStyle = 'max-height: 200px';

    public $paginationType = 'Scroll';

    public $activeClass = 'active-subject bg-level3 bg-opacity-15';
    public $unreadClass = 'font-semibold';

    protected $channelId;
    protected $activeDiscussionId;

    public function created()
    {
        $this->initializeBox();
    }

    public function query()
    {
        $this->channelId = $this->store('channel_id') ?: $this->parameter('id');
        $this->activeDiscussionId = $this->store('activeDiscussionId');

        $query = Channel::find($this->channelId)->subjects()->with('read', 'lastDiscussion.read');

        return $this->box ?

            $query->whereHas('box', fn($q) => $q->where('box', $this->box)) :

            $query->doesntHave('box');
    }

    public function render($discussion)
    {
        return _Link($discussion->subject)
            ->class('text-sm text-gray-600 pl-6 pr-4 pb-2')
            ->class($this->activeDiscussionId == $discussion->id ? $this->activeClass : '')
            ->class($discussion->hasUnreadDiscussions() ? $this->unreadClass : '')
            ->get('channel', ['id' => $discussion->channel_id, 'discussion_id' => $discussion->id])
            ->onSuccess(function($e) use($discussion){
                $e->inPanel('channel-view-panel');
                $e->setHistory('discussions', [
                    'channel_id' => $discussion->channel_id,
                    'discussion_id' => $discussion->id,
                ]);
                $e->removeClass($this->unreadClass);
                $e->activate();
            });
    }

}
