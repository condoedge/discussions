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
        $this->discussion = $this->discussionId ? Discussion::find($this->discussionId) : null;

        //Security
        $securityChannel = $this->discussion ? $this->discussion->channel : $this->channel;
        if ($securityChannel && !auth()->user()->can('view', $securityChannel)) {
            abort(403);
        }

        $this->itemsWrapperClass = 'channel-scroll overflow-y-auto mini-scroll py-10';
        $this->itemsWrapperStyle = $this->discussionId ?
                                    'height: calc(100vh - 140px)' :
                                    'height: calc(100vh - 352px)';

        $this->pusherRefresh = Discussion::pusherRefresh();

        $this->initializeBox();
    }

    public function query()
    {
        if($this->discussionId)
            return [$this->discussion];

        if($this->channel){
            $query = $this->channel->discussions()
                ->whereNull('discussion_id')
                ->with('box', 'discussions.addedBy', 'channel')
                ->orderByDesc('created_at');

            return $this->box ?

                $query->whereHas('box', fn($q) => $q->where('box', $this->box)) :

                $query->doesntHave('box');
        }
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
                        ->inPanel('channel-view-panel')
                )

            )->class('p-4 text-xl font-bold bg-white border-b border-gray-200');
    }

    public function render($discussion)
    {
        $card = _Rows(
            _FlexEnd(
                $discussion->isArchived ?
                    $this->discussionAction('archive-1', 'unarchiveDiscussion', $discussion) :
                    $this->discussionAction('archive-1', 'archiveDiscussion', $discussion),

                $discussion->isTrashed ?
                    $this->discussionAction('trash', 'untrashDiscussion', $discussion) :
                    $this->discussionAction('trash', 'trashDiscussion', $discussion),

            )->class('absolute top-6 right-4'),
            with(new SingleDiscussionCard([
                'discussion_id' => $discussion->id
            ]))->class('py-3')
        )->class('relative max-w-2xl mx-auto w-full px-2');

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

    protected function discussionAction($icon, $method, $discussion)
    {
        return _Link()->icon(_Sax($icon))->class('text-gray-600 ml-2')
            ->balloon(ucfirst(str_replace('Discussion', '', $method)), 'up-right')
            ->selfPost($method, [
                'id' => $discussion->id,
            ])->inAlert()
            ->removeSelf();
    }

    public function archiveDiscussion($id)
    {
        $this->updateDiscussionBox($id, 1);

        return 'discussions.discussion-archived';
    }

    public function unarchiveDiscussion($id)
    {
        $this->updateDiscussionBox($id, 0);

        return 'discussions.discussion-removed-archive';
    }

    public function trashDiscussion($id)
    {
        $this->updateDiscussionBox($id, 2);

        return 'discussions.discussion-trashed';
    }

    public function untrashDiscussion($id)
    {
        $this->updateDiscussionBox($id, 0);

        return 'discussions.discussion-removed-trash';
    }

    protected function updateDiscussionBox($id, $box)
    {
        if($box){
            Discussion::findOrFail($id)->updateBox($box);
        }else{
            Discussion::findOrFail($id)->box()->delete();
        }
    }

    public function noItemsFound()
    {
        return '<div class="text-gray-600 mb-4 p-4 text-center">'.__('discussions.no-discussions').'</div>';
    }
}
