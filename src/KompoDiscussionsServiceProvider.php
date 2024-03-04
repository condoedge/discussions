<?php

namespace Kompo\Discussions;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Kompo\Discussions\Models\Channel;
use Kompo\Discussions\Models\Discussion;

class KompoDiscussionsServiceProvider extends ServiceProvider
{
    use \Kompo\Routing\Mixins\ExtendsRoutingTrait;

    protected $configDirs = [
        // 'kompo-discussions' => __DIR__.'/../config/kompo-discussions.php',
    ];
    
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        Channel::class => Policies\ChannelPolicy::class,
        Discussion::class => Policies\DiscussionPolicy::class,
    ];

    protected $morphRelationsMap = [
        // 'discussion' => 'App\Models\Discussion',
    ];

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadHelpers();

        $this->registerPolicies();

        $this->extendRouting(); //otherwise Route::layout doesn't work

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'discussions');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'kompo-discussions');

        //Usage: php artisan vendor:publish --provider="Kompo\KompoDiscussionsServiceProvider"
        $this->publishes([
            __DIR__.'/../../config/kompo.php' => config_path('kompo.php'),
        ]);

        //Usage: php artisan vendor:publish --tag="kompo-discussions-config"
        $this->publishes(collect($this->configDirs)->map(
            fn($path, $key) => [$path => config_path($key . '.php')]
        )->toArray(), 'kompo-discussions-config');

        $this->loadConfig();

        $this->loadRelationsMorphMap();
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

    }

    protected function loadHelpers()
    {
        $helpersDir = __DIR__.'/Helpers';

        $autoloadedHelpers = collect(\File::allFiles($helpersDir))->map(fn($file) => $file->getRealPath());

        $packageHelpers = [
        ];

        $autoloadedHelpers->concat($packageHelpers)->each(function ($path) {
            if (file_exists($path)) {
                require_once $path;
            }
        });
    }

    protected function registerPolicies()
    {
        foreach ($this->policies as $key => $value) {
            \Gate::policy($key, $value);
        }
    }

    protected function loadConfig()
    {
        foreach ($this->configDirs as $key => $path) {
            $this->mergeConfigFrom($path, $key);
        }
    }
    
    /**
     * Loads a relations morph map.
     */
    protected function loadRelationsMorphMap()
    {
        Relation::morphMap($this->morphRelationsMap);
    }
}
