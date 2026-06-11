<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Components\Traits\ScrollToOnLoadTrait;
use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;
use Condoedge\Utils\Kompo\Common\Query;

class ChannelDiscussionsPanel extends Query
{
    use DiscussionBoxTrait,
        ScrollToOnLoadTrait;

    public $id = 'channel-discussion-panel';

    public $topPagination = true;
    public $bottomPagination = false;

    public $paginationType = 'Scroll';

    public $itemsWrapperClass = 'channel-scroll overflow-y-auto mini-scroll py-4 px-4';

    protected $channelId;
    protected $channel;
    protected $discussionId;
    protected $discussion;

    public function booted()
    {
        $this->activateScroll('#discussion-card-', '.channel-scroll');
    }

    public function created()
    {
        $this->channelId = $this->store('channel_id') ?: $this->parameter('id');
        $this->channel = Channel::find($this->channelId);
        $this->discussionId = $this->parameter('discussion_id');
        $this->discussion = $this->discussionId ?
            Discussion::with('box', 'addedBy', 'channel', 'read', 'files', 'reads.user')->find($this->discussionId) :
            null;

        $securityChannel = $this->discussion ? $this->discussion->channel : $this->channel;
        if ($securityChannel && !auth()->user()->can('view', $securityChannel)) {
            abort(403);
        }

        // Subscribe on the viewed channel's own team (it may differ from the user's current team)
        $this->pusherRefresh = Discussion::pusherRefresh($securityChannel?->team_id);

        $this->initializeBox();
    }

    public function query()
    {
        if($this->discussionId)
            return [$this->discussion];

        if($this->channel)
            return $this->channel->discussions()
                ->topLevel()
                ->inBox($this->box)
                ->with('box', 'addedBy', 'discussions.addedBy', 'channel', 'read', 'files', 'reads.user')
                ->orderByDesc('created_at');
    }

    public function top()
    {
        if($this->channel)
            return _FlexBetween(

                !$this->discussionId ?
                    _Link($this->channel->name)->class('truncate')
                        ->href('discussions', [
                            'channel_id' => $this->channelId,
                        ]) :
                    _Flex(
                        _Link($this->channel->name)->icon('arrow-left')
                            ->class('text-lg mr-4 text-level1')
                            ->href('discussions', [
                                'channel_id' => $this->channelId
                            ]),
                        _Link($this->discussion->subject)->class('truncate')
                    ),


                _FlexEnd(
                    (!auth()->user()->can('delete', $this->channel) || $this->discussionId) ? null :
                        _DeleteLink()->byKey($this->channel)->class('text-sm text-level1 mr-2')
                            ->redirect('discussions'),

                    _Link()->icon(_Sax('setting-2',20))->class('mr-8 md:mr-0')
                        ->get('channel-settings', ['id' => $this->channelId])
                        ->inModal()
                )

            )->class('p-4 text-xl font-bold bg-white border-b border-gray-200');
    }

    public function render($discussion)
    {
        $isCurrentUser = $discussion->isOwn();

        $flexDirection = $isCurrentUser ? 'flex-row-reverse' : 'flex-row';
        $alignItems = $isCurrentUser ? 'items-end' : 'items-start';

        $card = _Flex(
            _Rows(
                $discussion->cardWithActions(!$isCurrentUser)
            )->class('max-w-2xl px-2')

        )->class('mb-4 gap-2 ' . $flexDirection . ' ' . $alignItems);

        if (!$discussion->read) {
            $this->scrollToId = $this->scrollToId ?: $discussion->id;
            $discussion->markRead();
        }

        return $card;
    }

    public function bottom()
    {
        return $this->discussionId ? null : _Panel(
            new DiscussionForm(null, [
                'channel_id' => $this->channelId
            ])
        )->id('channel-discussion-form')
        ->class('max-w-2xl mx-auto w-full pl-9');
    }

    public function noItemsFound()
    {
        return '<div class="text-gray-600 mb-4 p-4 text-center">'.__('discussions.no-discussions').'</div>';
    }
}
