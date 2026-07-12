<?php

namespace Tests\Feature;

use Illuminate\Routing\Router;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\RouterServiceProvider;
use Tests\TestCase;

class ConfiguredDiscoveryTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('router.groups', [
            'api' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
                'prefix' => 'api/v1',
                'name' => 'api.v1.',
                'middleware' => ['api'], 'domain' => '{tenant}.example.test',
                'not_patterns' => ['*NeverController.php'],
                'patterns' => ['*.php'],
            ],
        ]);
    }

    public function test_provider_registers_group_routes_without_manual_discovery(): void
    {
        config()->set('router.groups', [
            'api' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
                'prefix' => 'api/v1', 'name' => 'api.v1.', 'middleware' => ['api'],
                'not_patterns' => ['*NeverController.php'],
            ],
        ]);
        (new RouterServiceProvider(app()))->boot();
        $routes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        $this->assertSame('api/v1/account/{account}/profiles', $routes['api.v1.account.profiles.index']->uri());
        $this->assertSame(['GET', 'HEAD'], $routes['api.v1.account.profiles.index']->methods());
        $this->assertSame(['api'], $routes['api.v1.account.profiles.index']->gatherMiddleware());
        $this->assertSame('{tenant}.example.test', $routes['api.v1.account.profiles.index']->getDomain());
        $this->assertTrue($routes['api.v1.account.profiles.index']->enforcesScopedBindings());
        $this->assertSame('api/v1/account/{account}/profiles/settings', $routes['api.v1.account.settings']->uri());
        $this->assertSame(['POST'], $routes['api.v1.account.settings']->methods());
        $this->assertTrue($routes['api.v1.account.settings']->preventsScopedBindings());
        $this->assertFalse($routes->has('api.v1.account.profiles.helper'));
        $this->assertFalse($routes->has('api.v1.never.index'));
    }

    public function test_manual_routes_coexist_with_discovered_routes(): void
    {
        app('router')->get('/manual-health', fn () => 'ok')->name('manual.health');

        $routes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        $this->assertTrue($routes->has('manual.health'));
        $this->assertTrue($routes->has('api.v1.account.profiles.index'));
    }

    public function test_discovered_routes_dispatch_through_their_group_domain_prefix_and_middleware(): void
    {
        $this->get('http://acme.example.test/api/v1/account/acme/profiles')
            ->assertOk();
    }

    public function test_discovery_routes_are_compatible_with_laravel_route_cache(): void
    {
        $this->assertNotNull($this->app->make('router')->getRoutes()->getByName('api.v1.account.profiles.index'));
        try {
            $this->artisan('route:cache')->assertExitCode(0);

            $this->assertTrue($this->app->make('files')->exists($this->app->getCachedRoutesPath()));
        } finally {
            $this->artisan('route:clear')->assertExitCode(0);
            $this->app->offsetUnset('routes.cached');
        }
    }

    public function test_cached_discovery_routes_load_into_a_fresh_router_without_discovery(): void
    {
        $compiledRoutes = app('router')->getRoutes()->compile();
        $router = new Router(app('events'), app());
        $router->setCompiledRoutes($compiledRoutes);

        $this->assertNotNull($router->getRoutes()->getByName('api.v1.account.profiles.index'));
        $this->assertCount(1, array_filter(
            $router->getRoutes()->getRoutes(),
            fn ($route) => $route->getName() === 'api.v1.account.profiles.index',
        ));
    }

    public function test_group_supports_multiple_paths_with_an_explicit_namespace(): void
    {
        require_once $this->fixturePath('MultiPath/Controllers/First/FirstController.php');
        require_once $this->fixturePath('MultiPath/Controllers/Second/SecondController.php');

        (new RouteDiscoveryManager(app('router')))->discover([
            'multi' => [
                'paths' => [
                    $this->fixturePath('MultiPath/Controllers/First'),
                    $this->fixturePath('MultiPath/Controllers/Second'),
                ],
                'namespace' => 'Tests\\Fixtures\\MultiPath\\Controllers\\',
                'prefix' => 'multi',
                'name' => 'multi.',
            ],
        ]);

        $routes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());
        $this->assertSame('multi/first', $routes['multi.first.index']->uri());
        $this->assertSame('multi/second', $routes['multi.second.index']->uri());
    }
}
