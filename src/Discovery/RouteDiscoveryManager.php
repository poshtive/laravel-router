<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

use Illuminate\Routing\Router;
use Poshtive\Router\RouteRegistrar;
use Poshtive\Router\Support\Path;

final class RouteDiscoveryManager
{
    private bool $registered = false;

    /** @var list<Diagnostic|string> */
    private array $diagnostics = [];

    /** @var array<string, true> */
    private array $diagnosedPaths = [];

    public function __construct(private Router $router) {}

    /** @param array<array-key, mixed> $groups */
    public function discover(array $groups): void
    {
        if ($this->registered || app()->routesAreCached()) {
            return;
        }

        $discovered = false;
        $registrar = app(RouteRegistrar::class, ['router' => $this->router]);
        $definitions = [];

        foreach ($groups as $name => $options) {
            $group = GroupConfig::fromArray((string) $name, (array) $options);

            foreach ($group->paths as $path) {
                if (! is_string($path)) {
                    $this->addDiagnostic('discovery_path_invalid', $group, get_debug_type($path), 'Configured discovery path is not a valid string.');

                    continue;
                }

                if (! is_dir($path)) {
                    $this->addDiagnostic('discovery_path_missing', $group, $this->displayPath($path), 'Configured discovery path does not exist.');

                    continue;
                }

                $namespace = $group->namespace ?? $this->applicationNamespaceFor($path);
                if ($namespace === null) {
                    $this->addDiagnostic('discovery_namespace_missing', $group, $this->displayPath($path), "Discovery path is outside the application directory; set the group's 'namespace' option.");

                    continue;
                }

                $discovered = true;
                array_push($definitions, ...$registrar->discover($group->withNamespace($namespace), $path));
            }
        }

        $registrar->registerDefinitions($definitions);
        $this->diagnostics = [...$this->diagnostics, ...$registrar->diagnostics()];
        $this->registered = $discovered;
    }

    /** @return list<Diagnostic|string> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    private function applicationNamespaceFor(string $path): ?string
    {
        $relative = Path::relative(app_path(), $path);

        return $relative === null ? null : rtrim(app()->getNamespace().Path::toNamespace($relative), '\\');
    }

    private function addDiagnostic(string $code, GroupConfig $group, string $path, string $message): void
    {
        $key = sprintf('%s:%s:%s', $code, $group->name, $path);

        if (isset($this->diagnosedPaths[$key])) {
            return;
        }

        $this->diagnosedPaths[$key] = true;
        $this->diagnostics[] = new Diagnostic(code: $code, severity: 'warning', group: $group->name, path: $path, message: $message);
    }

    private function displayPath(string $path): string
    {
        return Path::relative(base_path(), $path) ?? Path::normalize($path);
    }
}
