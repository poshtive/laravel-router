<?php

namespace Poshtive\Router;

use Illuminate\Support\ServiceProvider;

class RouterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/router.php' => \config_path('router.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/router.php',
            'router'
        );
    }
}
