<?php

namespace Poshtive\Router\Discovery;

use Illuminate\Routing\Router;
use Poshtive\Router\RouteRegistrar;

final class RouteDiscoveryManager
{
    private bool $registered = false;

    public function __construct(private Router $router) {}

    public function discover(array $groups): void
    {
        if ($this->registered || app()->routesAreCached()) {
            return;
        }

        $discovered = false;
        foreach ($groups as $name => $options) {
            $group = new RouteGroup((string) $name, (array) $options);
            foreach ($group->paths() as $path) {
                if (! is_string($path) || ! is_dir($path)) {
                    continue;
                }

                $discovered = true;

                $namespace = (string) ($group->options['namespace'] ?? '');
                if ($namespace === '' && str_starts_with($path, app_path())) {
                    $relative = trim(str_replace(app_path(), '', $path), DIRECTORY_SEPARATOR);
                    $namespace = app()->getNamespace().str_replace(DIRECTORY_SEPARATOR, '\\', $relative).'\\';
                }
                app(RouteRegistrar::class, [$this->router])
                    ->useBasePath(dirname($path))
                    ->useRootNamespace($namespace)
                    ->forGroup($group->options)
                    ->registerDirectory($path);
            }
        }

        $this->registered = $discovered;
    }
}
