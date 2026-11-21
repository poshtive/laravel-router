<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

use Illuminate\Routing\Router;
use Poshtive\Router\RouteRegistrar;
use Poshtive\Router\Support\Path;

final class RouteDiscoveryManager
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    public function __construct(private Router $router) {}

    /**
     * Discover and register routes during application boot, unless Laravel's
     * route cache is loaded.
     *
     * @param  array<array-key, mixed>  $groups
     */
    public function discover(array $groups): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        $reported = count($this->diagnostics);
        $this->run($groups, $this->strict());
        $this->log(array_slice($this->diagnostics, $reported));
    }

    /**
     * Run a full, non-strict discovery into this manager's router regardless of
     * the route cache, without logging. Used by router:check.
     *
     * @param  array<array-key, mixed>  $groups
     * @return list<Diagnostic>
     */
    public function inspect(array $groups): array
    {
        $reported = count($this->diagnostics);
        $this->run($groups, strict: false);

        return array_slice($this->diagnostics, $reported);
    }

    /** @param  array<array-key, mixed>  $groups */
    private function run(array $groups, bool $strict): void
    {
        $registrar = new RouteRegistrar($this->router, app()->isLocal());
        $definitions = [];

        foreach ($groups as $name => $options) {
            $group = GroupConfig::fromArray((string) $name, (array) $options);

            foreach ($group->paths as $path) {
                if (! is_string($path)) {
                    $this->warn('discovery_path_invalid', $group, get_debug_type($path), 'Configured discovery path is not a valid string.');

                    continue;
                }

                if (! is_dir($path)) {
                    $this->warn('discovery_path_missing', $group, $this->displayPath($path), 'Configured discovery path does not exist.');

                    continue;
                }

                $namespace = $group->namespace ?? $this->applicationNamespaceFor($path);
                if ($namespace === null) {
                    $this->warn('discovery_namespace_missing', $group, $this->displayPath($path), "Discovery path is outside the application directory; set the group's 'namespace' option.");

                    continue;
                }

                array_push($definitions, ...$registrar->discover($group, $path, $namespace));
            }
        }

        try {
            $registrar->registerDefinitions($definitions, $strict);
        } finally {
            $this->diagnostics = [...$this->diagnostics, ...$registrar->diagnostics()];
        }
    }

    /**
     * Strict mode throws on any error-severity diagnostic. By default it is on
     * in the local and testing environments so problems surface during
     * development, and off elsewhere so production boots with the valid routes.
     */
    public function strict(): bool
    {
        $strict = config('router.strict');

        return $strict === null ? app()->environment('local', 'testing') : (bool) $strict;
    }

    /** @return list<Diagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    private function applicationNamespaceFor(string $path): ?string
    {
        $relative = Path::relative(app_path(), $path);

        return $relative === null ? null : rtrim(app()->getNamespace().Path::toNamespace($relative), '\\');
    }

    private function warn(string $code, GroupConfig $group, string $path, string $message): void
    {
        $this->diagnostics[] = new Diagnostic($code, Severity::Warning, $message, $group->name, $path);
    }

    /**
     * @param  list<Diagnostic>  $diagnostics
     */
    private function log(array $diagnostics): void
    {
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic->severity !== Severity::Info) {
                app('log')->warning('[laravel-router] '.$diagnostic->message, $diagnostic->toArray());
            }
        }
    }

    private function displayPath(string $path): string
    {
        return Path::relative(base_path(), $path) ?? Path::normalize($path);
    }
}
