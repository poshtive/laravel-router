<?php

namespace Tests\Wayfinder;

use Laravel\Wayfinder\Route as WayfinderRoute;
use Poshtive\Router\Discovery\RouteDiscoveryManager;

class LiveDiscoveryTest extends WayfinderTestCase
{
    public function test_discovered_routes_are_consumable_by_wayfinder(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);

        $discoveredRoutes = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );

        $this->assertNotEmpty($discoveredRoutes, 'At least one discovered route must exist for Wayfinder to consume.');

        $wayfinderRoutes = collect($discoveredRoutes)->map(fn ($route) => new WayfinderRoute(
            $route,
            collect(),
            null,
            null,
        ));

        // Every discovered route must have a controller class for Wayfinder to generate types.
        $this->assertTrue(
            $wayfinderRoutes->every(fn (WayfinderRoute $wr) => $wr->hasController()),
            'All discovered routes must have a controller class for Wayfinder compatibility.'
        );

        // Verify specific routes are present with expected controllers.
        $controllers = $wayfinderRoutes->map(fn (WayfinderRoute $wr) => ltrim($wr->controller(), '\\'))->unique()->values();

        $this->assertContains(
            'Tests\\Fixtures\\Wayfinder\\Controllers\\UserController',
            $controllers->toArray(),
        );
        $this->assertContains(
            'Tests\\Fixtures\\Wayfinder\\Controllers\\User\\ProfileController',
            $controllers->toArray(),
        );
    }

    public function test_nested_controller_routes_are_consumable(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);

        // Find the nested ProfileController edit route.
        $routes = app('router')->getRoutes()->getRoutes();
        $profileRoute = collect($routes)->first(
            fn ($route) => $route->getAction('_laravel_router') !== null
                && $route->getActionName() === 'Tests\\Fixtures\\Wayfinder\\Controllers\\User\\ProfileController@edit',
        );

        $this->assertNotNull($profileRoute, 'Nested ProfileController edit route must be discovered.');
        $this->assertStringContainsString('user/{team}/profile/{profile}/edit', $profileRoute->uri());

        $wr = new WayfinderRoute($profileRoute, collect(), null, null);
        $this->assertTrue($wr->hasController());
        $this->assertSame('Tests\\Fixtures\\Wayfinder\\Controllers\\User\\ProfileController', ltrim($wr->controller(), '\\'));
        $this->assertSame('edit', $wr->method());
        $this->assertNotEmpty($wr->parameters()->toArray(), 'Nested route must expose parameters to Wayfinder.');
    }

    public function test_optional_parameter_routes_are_consumable(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);

        $routes = app('router')->getRoutes()->getRoutes();
        $searchRoute = collect($routes)->first(
            fn ($route) => $route->getAction('_laravel_router') !== null
                && $route->getActionName() === 'Tests\\Fixtures\\Wayfinder\\Controllers\\UserController@search',
        );

        $this->assertNotNull($searchRoute, 'Search route with optional parameter must be discovered.');
        $this->assertStringContainsString('{query?}', $searchRoute->uri());

        $wr = new WayfinderRoute($searchRoute, collect(), null, null);
        $this->assertTrue($wr->hasController());
        $this->assertTrue(
            $wr->parameters()->contains(fn ($param) => $param->name === 'query'),
            'Optional parameter must be visible to Wayfinder.',
        );
    }

    public function test_enum_parameter_routes_are_consumable(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);

        $routes = app('router')->getRoutes()->getRoutes();
        $updateRoute = collect($routes)->first(
            fn ($route) => $route->getAction('_laravel_router') !== null
                && $route->getActionName() === 'Tests\\Fixtures\\Wayfinder\\Controllers\\UserController@update',
        );

        $this->assertNotNull($updateRoute, 'Update route with enum parameter must be discovered.');
        $this->assertStringContainsString('{status}', $updateRoute->uri());

        $wr = new WayfinderRoute($updateRoute, collect(), null, null);
        $this->assertTrue($wr->hasController());
        $this->assertTrue(
            $wr->parameters()->contains(fn ($param) => $param->name === 'status'),
            'Enum parameter must be visible to Wayfinder.',
        );
    }

    public function test_multi_verb_routes_are_consumable(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);

        $routes = app('router')->getRoutes()->getRoutes();
        $updateRoute = collect($routes)->first(
            fn ($route) => $route->getAction('_laravel_router') !== null
                && $route->getActionName() === 'Tests\\Fixtures\\Wayfinder\\Controllers\\UserController@update',
        );

        $this->assertNotNull($updateRoute);
        $verbs = $updateRoute->methods();
        $this->assertContains('PUT', $verbs);
        $this->assertContains('PATCH', $verbs);

        $wr = new WayfinderRoute($updateRoute, collect(), null, null);
        $wayfinderVerbs = $wr->verbs()->map(fn ($v) => strtoupper($v->actual))->toArray();
        $this->assertContains('PUT', $wayfinderVerbs);
        $this->assertContains('PATCH', $wayfinderVerbs);
    }
}
