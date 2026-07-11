<?php

namespace Poshtive\Router;

use Illuminate\Support\ServiceProvider;
use Poshtive\Router\Discovery\RouteDiscoveryManager;

class RouterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/router.php' => \config_path('router.php'),
        ], 'config');

        if ($this->app->bound('router') && config('router.enabled', true)) {
            $this->app->make(RouteDiscoveryManager::class)->discover((array) config('router.groups', []));
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/router.php',
            'router'
        );

        $this->app->singleton(RouteDiscoveryManager::class, fn ($app) => new RouteDiscoveryManager($app->make('router')));
    }
}
