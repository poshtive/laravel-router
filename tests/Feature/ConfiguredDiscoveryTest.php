<?php

namespace Tests\Feature;

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
                'not_patterns' => ['*Never.php'],
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
            ],
        ]);
        (new RouterServiceProvider(app()))->boot();
        $routes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        $this->assertSame('api/v1/account/{account}/profiles', $routes['api.v1.account.profiles.index']->uri());
        $this->assertSame(['GET', 'HEAD'], $routes['api.v1.account.profiles.index']->methods());
        $this->assertSame(['api'], $routes['api.v1.account.profiles.index']->gatherMiddleware());
        $this->assertSame('{tenant}.example.test', $routes['api.v1.account.profiles.index']->getDomain());
        $this->assertSame('api/v1/account/{account}/profiles/settings', $routes['api.v1.account.settings']->uri());
        $this->assertSame(['POST'], $routes['api.v1.account.settings']->methods());
        $this->assertFalse($routes->has('api.v1.account.profiles.helper'));
    }
}
