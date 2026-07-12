<?php

declare(strict_types=1);

namespace Poshtive\Router;

use Illuminate\Support\ServiceProvider;
use Poshtive\Router\Console\RouterDiagnoseCommand;
use Poshtive\Router\Console\RouterListCommand;
use Poshtive\Router\Discovery\RouteDiscoveryManager;

class RouterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/router.php' => \config_path('router.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([RouterListCommand::class, RouterDiagnoseCommand::class]);
        }

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
