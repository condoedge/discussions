<?php

namespace Kompo\Discussions\Services;

use Illuminate\Support\Facades\Route;
use Kompo\Discussions\Components\ChannelDiscussionsPanel;
use Kompo\Discussions\Components\ChannelSettingsForm;
use Kompo\Discussions\Components\ChannelSubjectsList;
use Kompo\Discussions\Components\ChannelsView;
use Kompo\Discussions\Components\DiscussionForm;
use Kompo\Discussions\Components\ProjectDiscussionThread;

class DiscussionsService
{
    public static function setRoutesChannelsView()
    {
        static::discussionBoxRoutes('discussions', '/{channel_id?}/{discussion_id?}', ChannelsView::class);

        Route::get('project-discussion/{id}', ProjectDiscussionThread::class)->name('project-discussion');
    }

    public static function setRoutesChannelDiscussions()
    {
        static::discussionBoxRoutes('channel', '/{id?}/{discussion_id?}', ChannelDiscussionsPanel::class);

        Route::get('discussion/reply/{discussion_id?}', DiscussionForm::class)->name('discussion-reply');
    }

    public static function setRoutesChannelSubjects()
    {
        static::discussionBoxRoutes('channel-subjects', '/{id?}', ChannelSubjectsList::class);
    }

    public static function setRoutesChannelSettings()
    {
        Route::get('channel-edit/{id?}', ChannelSettingsForm::class)->name('channel-settings');
    }

    public static function setAllRoutes()
    {
        static::setRoutesChannelsView();
        static::setRoutesChannelDiscussions();
        static::setRoutesChannelSubjects();
    }

    public static function setAllModalRoutes()
    {
        static::setRoutesChannelSettings();
    }

    /**
     * Navbar icon with a real-time unread badge: server-rendered count, incremented
     * live by the per-user DiscussionNotification broadcasts (badge appears on the
     * first event when starting at zero).
     */
    public static function navbarIndicator()
    {
        if (!auth()->id()) {
            return null;
        }

        $unread = \Kompo\Discussions\Models\Discussion::unreadCountForUser();

        $channel = 'discussion-user.'.auth()->id();

        // One handler for both events: new messages increment the badge; the
        // authoritative count (broadcast right after the user reads) sets/clears it
        $badgeJs = '({ response }) => {
            const b = document.getElementById("discussions-unread-badge");
            if (!b) return;
            const d = (response && response.data) || {};
            let n;
            if (typeof d.count !== "undefined") {
                n = parseInt(d.count, 10) || 0;
            } else {
                n = (parseInt(b.dataset.count || "0", 10) || 0) + 1;
            }
            b.dataset.count = n;
            b.textContent = n > 99 ? "99+" : n;
            b.style.display = n > 0 ? "" : "none";
        }';

        return _Div(
            _Link()->icon(_Sax('message', 30))
                ->balloon('discussions.title', 'down')
                ->href('discussions')
                ->class('text-level1 flex items-center'),

            _Html($unread > 99 ? '99+' : $unread)
                ->id('discussions-unread-badge')
                ->attr(['data-count' => $unread])
                ->class('absolute -top-1 -right-3 bg-red-500 text-white rounded-full px-2 text-xs')
                ->style($unread ? '' : 'display:none'),

            // _Html has no interactions layer; a _Hidden carrier owns the socket
            // events and drives the badge above by id
            _Hidden()
                ->onSocketEvents($channel, [
                    \Kompo\Discussions\Events\DiscussionNotification::BROADCAST_NAME,
                    \Kompo\Discussions\Events\DiscussionUnreadCount::BROADCAST_NAME,
                ])->run($badgeJs),
        )->class('relative flex items-center');
    }

    /**
     * App-wide "you received a message" toast (bottom-right, 5s). Mount once in the
     * host's layout (e.g. the navbar komponent): listens on the user's personal
     * private channel, so only actual channel participants receive the payload.
     */
    public static function messageToasts()
    {
        if (!auth()->id()) {
            return null;
        }

        return \Condoedge\Utils\Kompo\Chat\ChatScripts::messageToastListener([
            'channel' => 'discussion-user.'.auth()->id(),
            'event' => '.'.\Kompo\Discussions\Events\DiscussionNotification::BROADCAST_NAME,
            'titleText' => __('discussions.new-message'),
            'urlTemplate' => route('discussions', ['channel_id' => '__ID__']),
            'suppressPathPrefix' => '/discussions',
            'durationMs' => 5000,
        ]);
    }

    protected static function discussionBoxRoutes($slug, $params, $kompoClass)
    {
        Route::get($slug.$params, $kompoClass)->name($slug);
    
        Route::get($slug.'-archive'.$params, $kompoClass)->name($slug.'.archive');
    
        Route::get($slug.'-trash'.$params, $kompoClass)->name($slug.'.trash');
    }
}