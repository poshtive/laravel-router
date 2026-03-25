<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Poshtive\Router\RouterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RouterServiceProvider::class];
    }

    protected function packageBasePath(): string
    {
        return dirname(__DIR__);
    }

    protected function fixturePath(string $path): string
    {
        return $this->packageBasePath().'/tests/Fixtures/'.ltrim($path, '/');
    }
}
