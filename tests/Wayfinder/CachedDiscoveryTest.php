<?php

namespace Tests\Wayfinder;

use Illuminate\Routing\Router;
use Laravel\Wayfinder\Route as WayfinderRoute;
use Poshtive\Router\Discovery\RouteDiscoveryManager;

class CachedDiscoveryTest extends WayfinderTestCase
{
    public function test_routes_from_route_cache_are_consumable_by_wayfinder(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);

        // Collect routes before caching for comparison.
        $preCachedRoutes = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );
        $this->assertNotEmpty($preCachedRoutes);

        $preCachedActionNames = collect($preCachedRoutes)
            ->map(fn ($route) => $route->getActionName())
            ->values()
            ->toArray();

        // Simulate route cache by compiling routes and loading into a fresh router.
        $compiledRoutes = app('router')->getRoutes()->compile();
        $router = new Router(app('events'), app());
        $router->setCompiledRoutes($compiledRoutes);

        $cachedRoutes = $router->getRoutes()->getRoutes();
        $discoveredFromCache = array_filter(
            $cachedRoutes,
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );
        $this->assertCount(
            count($preCachedRoutes),
            $discoveredFromCache,
            'Route cache must preserve the same number of discovered routes.',
        );

        $cachedActionNames = collect($discoveredFromCache)
            ->map(fn ($route) => $route->getActionName())
            ->values()
            ->toArray();

        $this->assertSame(
            $preCachedActionNames,
            $cachedActionNames,
            'Route cache must preserve the same controller actions as live discovery.',
        );

        // Verify Wayfinder can consume cached routes.
        $wayfinderRoutes = collect($discoveredFromCache)->map(fn ($route) => new WayfinderRoute(
            $route,
            collect(),
            null,
            null,
        ));

        $this->assertTrue(
            $wayfinderRoutes->every(fn (WayfinderRoute $wr) => $wr->hasController()),
            'All cached routes must have a controller class for Wayfinder compatibility.',
        );

        // Verify nested route exists in cache.
        $profileRoute = collect($discoveredFromCache)->first(
            fn ($route) => $route->getActionName() === 'Tests\\Fixtures\\Wayfinder\\Controllers\\User\\ProfileController@edit',
        );
        $this->assertNotNull($profileRoute, 'Nested route must survive route cache.');
        $this->assertStringContainsString('user/{team}/profile/{profile}/edit', $profileRoute->uri());
    }

    public function test_wayfinder_contract_equivalent_between_live_and_cached(): void
    {
        // Live discovery.
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);

        $liveRoutes = collect(
            array_filter(
                app('router')->getRoutes()->getRoutes(),
                fn ($route) => $route->getAction('_laravel_router') !== null,
            )
        );

        // Build live Wayfinder contract.
        $liveContract = $liveRoutes
            ->map(fn ($route) => [
                'controller' => (new WayfinderRoute($route, collect(), null, null))->controller(),
                'method' => (new WayfinderRoute($route, collect(), null, null))->method(),
                'uri' => $route->uri(),
                'verbs' => $route->methods(),
            ])
            ->sortBy('uri')
            ->values()
            ->toArray();

        // Simulate route cache.
        $compiledRoutes = app('router')->getRoutes()->compile();
        $cachedRouter = new Router(app('events'), app());
        $cachedRouter->setCompiledRoutes($compiledRoutes);

        $cachedRoutes = collect(
            array_filter(
                $cachedRouter->getRoutes()->getRoutes(),
                fn ($route) => $route->getAction('_laravel_router') !== null,
            )
        );

        // Build cached Wayfinder contract.
        $cachedContract = $cachedRoutes
            ->map(fn ($route) => [
                'controller' => (new WayfinderRoute($route, collect(), null, null))->controller(),
                'method' => (new WayfinderRoute($route, collect(), null, null))->method(),
                'uri' => $route->uri(),
                'verbs' => $route->methods(),
            ])
            ->sortBy('uri')
            ->values()
            ->toArray();

        $this->assertSame(
            $liveContract,
            $cachedContract,
            'Live and cached Wayfinder route contracts must be equivalent.',
        );
    }
}
