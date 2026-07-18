<?php

namespace Tests\Feature;

use Illuminate\Routing\Router;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Tests\TestCase;

class ConsoleCommandTest extends TestCase
{
    public function test_diagnose_command_reports_discovery_state(): void
    {
        $this->artisan('router:diagnose')
            ->expectsOutputToContain('Discovery: enabled')
            ->expectsOutputToContain('Groups: 2')
            ->expectsOutputToContain('Diagnostics:')
            ->assertExitCode(0);
    }

    public function test_list_command_is_available(): void
    {
        $this->artisan('router:list')
            ->assertExitCode(0);
    }

    public function test_list_command_filters_discovered_routes_by_path(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'configured' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        $this->artisan('router:list', ['--path' => 'profiles'])
            ->expectsOutputToContain('account/{account}/profiles')
            ->doesntExpectOutputToContain('account/settings')
            ->assertExitCode(0);
    }

    public function test_diagnose_command_lists_discovery_diagnostics(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'diagnostics' => [
                'paths' => [$this->fixturePath('Diagnostics/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Diagnostics\\Controllers\\',
                'patterns' => ['HiddenController.php'],
            ],
        ]);

        $this->artisan('router:diagnose')
            ->expectsOutputToContain('Diagnostics:')
            ->expectsOutputToContain('DoNotDiscover')
            ->assertExitCode(0);
    }

    public function test_list_command_filters_to_discovered_routes_by_default(): void
    {
        app('router')->get('/manual', fn () => 'ok')->name('manual');

        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        // Default: only discovered routes
        $this->artisan('router:list')
            ->expectsOutputToContain('account/{account}/profiles')
            ->doesntExpectOutputToContain('/manual')
            ->assertExitCode(0);

        // --all: includes manual routes too
        $this->artisan('router:list', ['--all' => true])
            ->expectsOutputToContain('manual')
            ->assertExitCode(0);
    }

    public function test_diagnose_reports_separate_route_counts(): void
    {
        app('router')->get('/manual', fn () => 'ok');

        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        $this->artisan('router:diagnose')
            ->expectsOutputToContain('Laravel routes:')
            ->expectsOutputToContain('Discovered routes:')
            ->assertExitCode(0);
    }

    public function test_discovered_routes_carry_package_metadata_in_action(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        $discoveredRoutes = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );

        $this->assertNotEmpty($discoveredRoutes);
        foreach ($discoveredRoutes as $route) {
            $meta = $route->getAction('_laravel_router');
            $this->assertArrayHasKey('id', $meta);
            $this->assertArrayHasKey('group', $meta);
            $this->assertSame('web', $meta['group']);
        }
    }

    public function test_route_cache_preserves_package_metadata(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        // Verify metadata is present before caching
        $preCached = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );
        $this->assertNotEmpty($preCached);

        // Compile routes and load into a fresh router to simulate route cache
        $compiledRoutes = app('router')->getRoutes()->compile();
        $router = new Router(app('events'), app());
        $router->setCompiledRoutes($compiledRoutes);

        $cachedRoutes = $router->getRoutes()->getRoutes();
        $discoveredFromCache = array_filter(
            $cachedRoutes,
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );
        $this->assertCount(count($preCached), $discoveredFromCache);
    }
}
