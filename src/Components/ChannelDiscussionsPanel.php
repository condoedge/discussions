<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;
use Condoedge\Utils\Kompo\Chat\ChatMessagesQuery;

class ChannelDiscussionsPanel extends ChatMessagesQuery
{
    use DiscussionBoxTrait;

    public $id = 'channel-discussion-panel';

    // Kit recipe (p-6 + [&>div] gap/col-reverse) with 'channel-scroll' kept for existing
    // references (SingleDiscussionCard's reply scrollTo container)
    public $itemsWrapperClass = 'channel-scroll [&>div]:gap-4 [&>div]:flex [&>div]:flex-col-reverse p-6 overflow-y-auto mini-scroll flex-1 min-h-0';

    protected $channelId;
    protected $channel;
    protected $discussionId;
    protected $discussion;

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
        // The kit bubble handles own/other alignment itself; the avatar shows on
        // other users' messages only (matching the previous look)
        $card = $discussion->cardWithActions(!$discussion->isOwn());

        if (!$discussion->read) {
            $discussion->markRead();
        }

        return $card;
    }

    /**
     * The messages panel + composer column. The composer lives OUTSIDE the Query
     * on purpose: the send button's ->refresh() then only re-renders the messages,
     * so typing is never interrupted and sends can pipeline back-to-back.
     */
    public static function withComposer($channelId, $box = null)
    {
        return _Rows(
            new static([
                'channel_id' => $channelId,
                'box' => $box,
            ]),

            _Div(
                new DiscussionForm(null, [
                    'channel_id' => $channelId,
                ])
            )->id('channel-discussion-form')
            ->class('w-full bg-white shrink-0')
        )->class('discussions-chat-column');
    }

    public function bottom()
    {
        // Remounts (and re-snaps) on every refresh of this Query
        return $this->snapToBottomOnLoad();
    }

    public function noItemsFound()
    {
        return '<div class="text-gray-600 mb-4 p-4 text-center">'.__('discussions.no-discussions').'</div>';
    }
}
