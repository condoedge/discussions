<?php

namespace Kompo\Discussions\Components;

use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;
use Kompo\Query;

class ChannelsList extends Query
{
    use DiscussionBoxTrait;

	public $perPage = 30;

    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';
    public $itemsWrapperStyle = 'height: calc(100vh - 182px)';

    public $paginationType = 'Scroll';

    public $activeClass = 'active-channel bg-gray-400 text-level1 border-l-4 border-level1';
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

    public function render($channel, $key)
    {
        $isActiveChannel = $channel->id == $this->activeChannelId;

        if($isActiveChannel)
            $this->activeIndex = $key;

        return _Rows(
            _FlexBetween(

                _Html($channel->display),

                !$channel->subjects_count ? null :
                    _Html()->icon('dots-vertical')->class('text-gray-500'),

            )->class('cursor-pointer py-2 px-4 text-sm uppercase'),

            !$channel->subjects_count ? null :

                _Panel(
                    $isActiveChannel ? new ChannelSubjectsList([
                        'channel_id' => $channel->id,
                        'activeDiscussionId' => $this->activeDiscussionId,
                        'box' => $this->box,
                    ]) : null
                )->class('channel-subjects')
                ->id($this->subjectsPanelId($channel->id))

        )->class('hover:bg-gray-100')
        ->class($channel->hasUnreadDiscussions() ? $this->unreadClass : '')
        ->onClick(function($e) use($channel){

            $this->loadSubjects($e, $channel->id);

            $e->get($this->routeChannel, ['id' => $channel->id])
                ->onSuccess(function($e) use($channel){
                    $e->inPanel('channel-view-panel');
                    $e->setHistory($this->routeDiscussions, [
                        'channel_id' => $channel->id
                    ]);
                });
        });
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
