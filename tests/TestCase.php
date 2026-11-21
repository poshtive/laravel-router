<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\RouteCacheCommand;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Poshtive\Router\RouterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        // Real applications register RouteServiceProvider through withRouting(); it
        // loads bootstrap/cache/routes-v7.php when routes are cached.
        return [RouteServiceProvider::class, RouterServiceProvider::class];
    }

    protected function packageBasePath(): string
    {
        return dirname(__DIR__);
    }

    protected function fixturePath(string $path): string
    {
        return $this->packageBasePath().'/tests/Fixtures/'.ltrim($path, '/');
    }

    /**
     * Run Laravel's route:cache for this test application.
     *
     * The stock command boots a fresh application from Testbench's skeleton,
     * which does not load this package or the test configuration. This variant
     * caches the current application's routes with the same serialization code.
     */
    protected function cacheRoutes(): void
    {
        $command = new class($this->app->make('files')) extends RouteCacheCommand
        {
            protected function getFreshApplicationRoutes()
            {
                return tap($this->laravel->make('router')->getRoutes(), function ($routes) {
                    $routes->refreshNameLookups();
                    $routes->refreshActionLookups();
                });
            }
        };
        $command->setLaravel($this->app);
        $this->app->make(ConsoleKernel::class)->registerCommand($command);

        $this->artisan('route:cache')->assertExitCode(0);
    }
}
