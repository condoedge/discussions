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

    protected static function discussionBoxRoutes($slug, $params, $kompoClass)
    {
        Route::get($slug.$params, $kompoClass)->name($slug);
    
        Route::get($slug.'-archive'.$params, $kompoClass)->name($slug.'.archive');
    
        Route::get($slug.'-trash'.$params, $kompoClass)->name($slug.'.trash');
    }
}