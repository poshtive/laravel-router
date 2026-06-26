<?php

namespace Tests\Unit;

use Illuminate\Routing\Router as IlluminateRouter;
use Poshtive\Router\Router;
use Poshtive\Router\RouteRegistrar;
use Tests\TestCase;

class RouterTest extends TestCase
{
    public function test_it_forwards_base_path_and_root_namespace_to_the_registrar(): void
    {
        $fakeRegistrar = new FakeRouteRegistrar($this->app->make(IlluminateRouter::class));
        $this->app->bind(RouteRegistrar::class, fn () => $fakeRegistrar);

        Router::create()
            ->useRootNamespace('Tests\\Fixtures\\RouteDiscovery\\')
            ->useBasePath($this->packageBasePath())
            ->discover($this->fixturePath('RouteDiscovery/Controllers'));

        $this->assertSame('Tests\\Fixtures\\RouteDiscovery\\', $fakeRegistrar->receivedRootNamespace);
        $this->assertSame($this->packageBasePath(), $fakeRegistrar->receivedBasePath);
        $this->assertSame($this->fixturePath('RouteDiscovery/Controllers'), $fakeRegistrar->receivedDirectory);
    }

    public function test_create_can_seed_constructor_arguments(): void
    {
        $fakeRegistrar = new FakeRouteRegistrar($this->app->make(IlluminateRouter::class));
        $this->app->bind(RouteRegistrar::class, fn () => $fakeRegistrar);

        Router::create('Seeded\\Namespace\\', '/tmp')
            ->discover('seeded-directory');

        $this->assertSame('Seeded\\Namespace\\', $fakeRegistrar->receivedRootNamespace);
        $this->assertSame('/tmp', $fakeRegistrar->receivedBasePath);
        $this->assertSame('seeded-directory', $fakeRegistrar->receivedDirectory);
    }
}

class FakeRouteRegistrar extends RouteRegistrar
{
    public string $receivedRootNamespace = '';

    public string $receivedBasePath = '';

    public string $receivedDirectory = '';

    public function useRootNamespace(string $rootNamespace): self
    {
        $this->receivedRootNamespace = $rootNamespace;

        return $this;
    }

    public function useBasePath(string $basePath): self
    {
        $this->receivedBasePath = $basePath;

        return $this;
    }

    public function registerDirectory(string $directory): void
    {
        $this->receivedDirectory = $directory;
    }
}
