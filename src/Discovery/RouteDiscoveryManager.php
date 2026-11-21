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
                if (! is_string($path)) {
                    $diag = new Diagnostic(
                        code: 'discovery_path_invalid',
                        severity: 'warning',
                        group: (string) $name,
                        path: get_debug_type($path),
                        message: 'Configured discovery path is not a valid string.',
                    );
                    $this->addDiagnostic($diag);

                    continue;
                }

                if (! is_dir($path)) {
                    $normalizedPath = $this->normalizePath($path);
                    $diag = new Diagnostic(
                        code: 'discovery_path_missing',
                        severity: 'warning',
                        group: (string) $name,
                        path: $normalizedPath,
                        message: 'Configured discovery path does not exist.',
                    );
                    $this->addDiagnostic($diag);

                    continue;
                }

                $discovered = true;

                $namespace = (string) ($group->options['namespace'] ?? '');
                if ($namespace === '' && ($relative = Path::relative(app_path(), $path)) !== null) {
                    $namespace = rtrim(app()->getNamespace().Path::toNamespace($relative), '\\').'\\';
                }
                $groupOptions = $group->options;
                if ($namespace !== '' && ! array_key_exists('namespace', $groupOptions)) {
                    $groupOptions['namespace'] = $namespace;
                }
                $registrar
                    ->useBasePath(dirname($path))
                    ->useRootNamespace($namespace)
                    ->forGroup($groupOptions);
                $groupDefs = $registrar->discoverDirectory($path);
                foreach ($groupDefs as $def) {
                    $def->group = (string) $name;
                }
                $definitions = array_merge($definitions, $groupDefs);
            }
        }

        $registrar->registerDefinitions($definitions);
        $this->diagnostics = array_merge($this->diagnostics, $registrar->diagnostics());

        $this->registered = $discovered;
    }

    /** @return list<Diagnostic|string> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    private function addDiagnostic(Diagnostic $diagnostic): void
    {
        $key = sprintf('%s:%s:%s', $diagnostic->code, $diagnostic->group, $diagnostic->path);

        if (isset($this->diagnosedPaths[$key])) {
            return;
        }

        $this->diagnosedPaths[$key] = true;
        $this->diagnostics[] = $diagnostic;
    }

    private function normalizePath(string $path): string
    {
        return Path::relative(base_path(), $path) ?? Path::normalize($path);
    }
}
