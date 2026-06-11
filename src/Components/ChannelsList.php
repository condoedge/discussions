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

    public $paginationType = 'Scroll';

    public $activeClass = 'active-channel bg-level1 bg-opacity-10 border-l-4 border-level1';
    public $unreadClass = 'font-semibold';

    protected $activeChannelId;
    protected $activeDiscussionId;

    public function created()
    {
        $this->activeChannelId = $this->store('active_channel_id');
        $this->activeDiscussionId = $this->store('active_discussion_id');

        // Listed channels can span several teams; subscribe to each of them.
        // The personal count event re-renders the list right after THIS user reads
        // (DiscussionsRead goes toOthers, so it alone would leave their own unread
        // pills stale until someone else's event).
        $this->pusherRefresh = array_merge(
            Discussion::pusherRefresh(Channel::forUser()->pluck('team_id')->all()),
            ['discussion-user.'.auth()->id() => [
                '.'.\Kompo\Discussions\Events\DiscussionUnreadCount::BROADCAST_NAME,
            ]],
        );

        $this->initializeBox();

        $this->noItemsFound = '<div class="py-2 px-4">'.__('discussions.no-channels').'</div>';
    }

    public function query()
    {
        $query = Channel::queryForUser()->withUnreadCount();

        return $this->box ?
            $query->whereHas('discussions', fn($q) => $q->inBox($this->box)) :
            $query->where(
                fn($q) => $q->doesntHave('discussions')->orWhereHas('discussions', fn($q1) => $q1->inBox(null))
            );
    }

    public function render($channel)
    {
        $isActiveChannel = $channel->id == $this->activeChannelId;
        $unreadCount = $channel->unread_count ?? 0;

        // Avatar initial from channel name
        $initial = strtoupper(substr($channel->display, 0, 1));

        // Last message info
        $lastDiscussion = $channel->lastDiscussion;
        $lastMessageTime = $lastDiscussion ? $lastDiscussion->created_at->diffForHumans() : null;
        $lastMessagePreview = $lastDiscussion ?
            ($lastDiscussion->summary ?: safeTruncate($lastDiscussion->html, 40)) :
            __('discussions.no-messages');

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
                        _Html($channel->display)->class('text-sm '.($unreadCount ? 'font-bold text-gray-900' : 'font-semibold text-gray-900')),
                        $lastMessageTime ?
                            _Html($lastMessageTime)->class('text-xs '.($unreadCount ? 'text-level1 font-semibold' : 'text-gray-500')) :
                            null
                    )->class('gap-4'),
                    _FlexBetween(
                        _Html($lastMessagePreview)->class('text-xs mt-0.5 '.($unreadCount ? 'text-gray-900 font-medium' : 'text-gray-600')),

                        // Unread count pill — the unmissable signal
                        !$unreadCount ? null :
                            _Html($unreadCount > 99 ? '99+' : $unreadCount)
                                ->class('bg-level1 text-white text-xs font-bold rounded-full px-1.5 ml-2 shrink-0'),
                    )->class('items-center')
                )->class('flex-1 min-w-0 ml-3'),

            )->class('cursor-pointer px-3 py-3 items-start'),

            // Subjects are muted for now: no expand icon, no per-subject sub-list

        )->class('hover:bg-gray-50 transition-colors border-b border-gray-100 channel-item')
        ->id('channel-'.$channel->id)
        ->class($isActiveChannel ? $this->activeClass : '')
        ->class($channel->hasUnreadDiscussions() ? 'bg-blue-50 ' . $this->unreadClass : '')
        ->onClick(function($e) use($channel){

            // Mark the clicked channel as active client-side (the list itself doesn't reload)
            $e->run('() => {
                setTimeout(() => {
                    $(".channel-item").removeClass("'.$this->activeClass.'");

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
        $channel = Channel::findOrFail($id);

        if (!auth()->user()->can('view', $channel)) {
            abort(403);
        }

        return ChannelDiscussionsPanel::withComposer($id, $this->box);
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
