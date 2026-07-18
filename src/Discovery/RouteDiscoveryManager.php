<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

use Illuminate\Routing\Router;
use Poshtive\Router\RouteRegistrar;

final class RouteDiscoveryManager
{
    private bool $registered = false;

    /** @var list<string> */
    private array $diagnostics = [];

    public function __construct(private Router $router) {}

    /** @param array<string, array<string, mixed>> $groups */
    public function discover(array $groups): void
    {
        if ($this->registered || app()->routesAreCached()) {
            return;
        }

        $discovered = false;
        $registrar = app(RouteRegistrar::class, [$this->router]);
        $definitions = [];
        foreach ($groups as $name => $options) {
            $group = new RouteGroup((string) $name, (array) $options);
            $registrar->useGroupName((string) $name);
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
                $groupOptions = $group->options;
                if ($namespace !== '' && ! array_key_exists('namespace', $groupOptions)) {
                    $groupOptions['namespace'] = $namespace;
                }
                $registrar
                    ->useBasePath(dirname($path))
                    ->useRootNamespace($namespace)
                    ->forGroup($groupOptions);
                $definitions = array_merge($definitions, $registrar->discoverDirectory($path));
            }
        }

        $registrar->registerDefinitions($definitions);
        $this->diagnostics = array_merge($this->diagnostics, $registrar->diagnostics());

        $this->registered = $discovered;
    }

    /** @return list<string> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
