<?php

namespace Kompo\Discussions;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;

class KompoDiscussionsServiceProvider extends ServiceProvider
{
    use \Kompo\Routing\Mixins\ExtendsRoutingTrait;

    protected $policies = [
        Channel::class => Policies\ChannelPolicy::class,
        Discussion::class => Policies\DiscussionPolicy::class,
    ];

    public function boot()
    {
        $this->loadHelpers();

        $this->registerPolicies();

        $this->extendRouting(); //otherwise Route::layout doesn't work

        $this->loadJSONTranslationsFrom(__DIR__.'/../resources/lang');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'kompo-discussions');

        $this->registerBroadcastChannels();
    }

    public function register()
    {
        //
    }

    protected function loadHelpers()
    {
        collect(\File::allFiles(__DIR__.'/Helpers'))
            ->each(fn($file) => require_once $file->getRealPath());
    }

    protected function registerPolicies()
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Authorizes the private channel DiscussionSent broadcasts on.
     * The host app still needs broadcasting enabled (BroadcastServiceProvider
     * + a configured BROADCAST_DRIVER) for live refresh to work.
     */
    protected function registerBroadcastChannels()
    {
        try {
            // Congruent with ChannelPolicy: channel participation doesn't require an
            // active team role (members can be cross-team), so participants of any of
            // the team's channels are authorized too — otherwise their subscription
            // 403s silently and they receive no live updates or whispers at all.
            Broadcast::channel('discussion.{teamId}', function ($user, $teamId) {
                return Channel::where('team_id', $teamId)->forUser($user->id)->exists()
                    || $user->teams()->whereKey($teamId)
                        ->wherePivotNull('terminated_at')
                        ->wherePivotNull('suspended_at')
                        ->exists();
            });

            // Personal channel for the app-wide "new message" toasts
            Broadcast::channel('discussion-user.{userId}', function ($user, $userId) {
                return (int) $user->id === (int) $userId;
            });
        } catch (\Throwable $e) {
            // Broadcasting driver not configured in this context (e.g. console); live refresh stays off
        }
    }
}
