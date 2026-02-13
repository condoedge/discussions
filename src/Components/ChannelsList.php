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

        return $this->box ?

            $query->whereHas('discussions', function($q){
                $q->whereHas('box', fn($q2) => $q2->where('box', $this->box));
            }) :

            $query->where(
                fn($q) => $q->doesntHave('discussions')->orWhereHas('discussions', fn($q1) => $q1->doesntHave('box'))
            );
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

        // Avatar initial from channel name
        $initial = strtoupper(substr($channel->display, 0, 1));

        // Last message info
        $lastDiscussion = $channel->lastDiscussion;
        $lastMessageTime = $lastDiscussion ? $lastDiscussion->created_at->diffForHumans() : null;
        $lastMessagePreview = $lastDiscussion ? \Str::limit($lastDiscussion->body, 40) : 'Aucun message';

        return _Rows(
            // Channel header
            _Flex(
                // Avatar circle with initial
                _Flex(
                    _Html($initial)->class('text-white font-bold text-sm')
                )->class('w-10 h-10 rounded-full bg-gradient-to-br from-level1 to-level2 flex items-center justify-center flex-shrink-0'),

                // Channel info
                _Rows(
                    _FlexBetween(
                        _Html($channel->display)->class('font-semibold text-gray-900 text-sm'),
                        $lastMessageTime ? _Html($lastMessageTime)->class('text-xs text-gray-500') : null
                    ),
                    _Html($lastMessagePreview)->class('text-xs text-gray-600 mt-0.5')
                )->class('flex-1 min-w-0 ml-3'),

                // Expand icon for subjects
                !$channel->subjects_count ? null :
                    _Html()->icon(_Sax('arrow-down-1', 16))->class('text-gray-400 ml-2 flex-shrink-0')

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
