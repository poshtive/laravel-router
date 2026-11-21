<?php

namespace Tests\Feature;

use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Tests\TestCase;

class InheritedActionsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('router.groups', [
            'inheritance' => [
                'paths' => [$this->fixturePath('Inheritance/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Inheritance\\Controllers\\',
            ],
        ]);
    }

    public function test_only_own_methods_and_discoverable_parents_and_traits_become_routes(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getAction('_laravel_router') !== null)
            ->mapWithKeys(fn ($route) => [$route->getName() => [implode('|', $route->methods()), $route->uri()]])
            ->sortKeys();

        $this->assertEquals([
            'buku.export' => ['GET|HEAD', 'buku/export'],
            'buku.index' => ['GET|HEAD', 'buku'],
            'buku.show' => ['GET|HEAD', 'buku/{id}'],
            'buku.store' => ['POST', 'buku'],
            'buku.terbitkan' => ['GET|HEAD', 'buku/{id}/terbitkan'],
            'penulis.index' => ['GET|HEAD', 'penulis'],
            'penulis.profil' => ['GET|HEAD', 'penulis/{id}/profil'],
            'plain-child.index' => ['GET|HEAD', 'plain-child'],
        ], $routes->all());
    }

    public function test_inherited_actions_dispatch_to_the_concrete_controller_with_parent_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('buku.index');

        $this->assertSame('Tests\\Fixtures\\Inheritance\\Controllers\\BukuController@index', $route->getActionName());
        $this->assertSame(['auth'], $route->gatherMiddleware());
    }

    public function test_only_and_except_cannot_be_combined(): void
    {
        config()->set('router.strict', false);

        $manager = new RouteDiscoveryManager(app('router'));
        $manager->discover([
            'conflict' => [
                'paths' => [$this->fixturePath('InheritanceConflict/Controllers')],
                'namespace' => 'Tests\\Fixtures\\InheritanceConflict\\Controllers\\',
            ],
        ]);

        $this->assertNull(app('router')->getRoutes()->getByName('conflicting-filter.index'));
        $this->assertStringContainsString('cannot use #[Only] and #[Except] together', implode("\n", $manager->diagnostics()));
    }
}
