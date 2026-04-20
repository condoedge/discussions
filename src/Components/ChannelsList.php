<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;
use Condoedge\Utils\Kompo\Common\Query;

class ChannelsList extends Query
{
    use DiscussionBoxTrait;

	public $perPage = 30;

    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';
    public $itemsWrapperStyle = 'height: calc(100vh - 182px)';

    public $paginationType = 'Scroll';

    public $activeClass = 'active-channel bg-level1 bg-opacity-10 border-l-4 border-level1';
    public $unreadClass = 'font-semibold';

    protected $activeChannelId;
    protected $activeDiscussionId;

    public function created()
    {
        $this->activeChannelId = $this->store('active_channel_id');
        $this->activeDiscussionId = $this->store('active_discussion_id');

        $this->pusherRefresh = Discussion::pusherRefresh();

        $this->initializeBox();

        $this->noItemsFound = '<div class="py-2 px-4">'.__('discussions.no-channels').'</div>';
    }

    public function query()
    {
        $query = Channel::queryForUser();

        $query = $this->box ?
            $query->whereHas('discussions', fn ($q) => $q->whereHas('box', fn ($q2) => $q2->where('box', $this->box))) :
            $query->where(fn ($q) => $q->doesntHave('discussions')->orWhereHas('discussions', fn ($q1) => $q1->doesntHave('box')));

        $search = $this->store('channel_search') ?: request('channel_search');
        if ($search) {
            $query->where('channels.name', 'like', '%' . $search . '%');
        }

        $filter = $this->store('filter') ?: request('filter', 'all');
        if ($filter === 'unread') {
            $query = $query->get()->filter(fn ($c) => $c->hasUnreadDiscussions());
        }

        return $query;
    }

    protected function channelsTitle($label, $rightButtons = null)
    {
        return _FlexBetween(
            _Html($label)
                ->class('font-bold text-sm text-gray-500 leading-wide uppercase'),
            $rightButtons
        )->class('py-2 px-4 border-b border-gray-200');

    }

    public function render($channel)
    {
        $isActiveChannel = $channel->id == $this->activeChannelId;
        $hasUnread = $channel->hasUnreadDiscussions();

        $lastDiscussion = $channel->lastDiscussion;
        $lastMessageTime = $lastDiscussion ? $lastDiscussion->created_at->diffForHumans() : null;
        $lastMessagePreview = $lastDiscussion ? \Str::limit(strip_tags($lastDiscussion->html ?? $lastDiscussion->summary ?? ''), 40) : __('discussions.no-message-yet');

        return _Rows(
            _Flex(
                // Channel icon avatar using custom icon + color
                _Flex(
                    _Sax($channel->getIconName(), 18)->class('text-white'),
                )->class('w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0')
                 ->style('background-color: ' . $channel->getColorHex() . ';'),

                // Channel info
                _Rows(
                    _FlexBetween(
                        _Flex(
                            _Html($channel->display)->class('font-semibold text-gray-900 text-sm truncate'),
                            $channel->is_private ? _Sax('lock', 12)->class('text-gray-400 flex-shrink-0') : null,
                        )->class('gap-1 items-center min-w-0'),
                        $lastMessageTime ? _Html($lastMessageTime)->class('text-xs text-gray-500 flex-shrink-0 ml-2') : null,
                    ),
                    _FlexBetween(
                        _Html($lastMessagePreview)->class('text-xs text-gray-600 mt-0.5 truncate'),
                        $hasUnread ? _Html()->class('w-2 h-2 rounded-full bg-greenmain flex-shrink-0 ml-2 mt-1') : null,
                    ),
                )->class('flex-1 min-w-0 ml-3'),

                !$channel->subjects_count ? null :
                    _Html()->icon(_Sax('arrow-down-1', 16))->class('text-gray-400 ml-2 flex-shrink-0'),

            )->class('cursor-pointer px-3 py-3 items-start'),

            // Subjects panel
            !$channel->subjects_count ? null :
                _Panel(
                    $isActiveChannel ? new ChannelSubjectsList([
                        'channel_id' => $channel->id,
                        'activeDiscussionId' => $this->activeDiscussionId,
                        'box' => $this->box,
                    ]) : null
                )->class('channel-subjects bg-gray-50')
                ->id($this->subjectsPanelId($channel->id))

        )->class('hover:bg-gray-50 transition-colors border-b border-gray-100 channel-item')
        ->id('channel-'.$channel->id)
        ->class($isActiveChannel ? $this->activeClass : '')
        ->class($channel->hasUnreadDiscussions() ? 'bg-blue-50 ' . $this->unreadClass : '')
        ->onClick(function($e) use($channel){

            $this->loadSubjects($e, $channel->id);

            // Marking the channel as active using js
            $e->run('() => {
                setTimeout(() => {
                    // Remove active class from all channels
                    $(".channel-item").removeClass("'.$this->activeClass.'");

                    // Add active class to the clicked channel
                    const channelElement = $("#channel-'.$channel->id.'");
                    if(channelElement.length) channelElement.addClass("'.$this->activeClass.'");
                }, 100);
            }');

            $e->selfGet('getChannelView', ['id' => $channel->id])
                ->onSuccess(function($e) use($channel){
                    $e->inPanel('channel-view-panel');
                    $e->setHistory($this->routeDiscussions, [
                        'channel_id' => $channel->id
                    ]);
                });
        });
    }

    public function getChannelView($id)
    {
        return new ChannelDiscussionsPanel([
            'channel_id' => $id,
            'box' => $this->box,
        ]);
    }

    protected function loadSubjects($komponent, $channelId)
    {
        return $komponent->get($this->routeChannelSubjects, ['id' => $channelId])
            ->onSuccess(function($e) use($channelId) {
                $e->inPanel($this->subjectsPanelId($channelId));
                $e->removeClass($this->unreadClass);
                $e->activate();
            });
    }

    protected function subjectsPanelId($channelId)
    {
        return 'channel-subjects'.$channelId;
    }

}
