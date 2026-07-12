<?php

namespace Tests\Feature;

use Tests\TestCase;

class DisabledDiscoveryTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('router.enabled', false);
        $app['config']->set('router.groups', [
            'test' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);
    }

    public function test_provider_does_not_discover_when_disabled(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\Configured'));

        $this->assertNull($route);
    }
}
