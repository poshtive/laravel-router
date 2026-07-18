<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

use Illuminate\Routing\Router;
use Poshtive\Router\RouteDefinition;
use Poshtive\Router\RouteRegistrar;

final class RouteDiscoveryManager
{
    private bool $registered = false;

    /** @var list<Diagnostic|string> */
    private array $diagnostics = [];

    /** @var array<string, true> */
    private array $diagnosedPaths = [];

    private ?DiscoveredRoutes $registry = null;

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
                $groupDefs = $registrar->discoverDirectory($path);
                foreach ($groupDefs as $def) {
                    $def->group = (string) $name;
                }
                $definitions = array_merge($definitions, $groupDefs);
            }
        }

        $registrar->registerDefinitions($definitions);
        $this->diagnostics = array_merge($this->diagnostics, $registrar->diagnostics());

        $this->buildRegistry($definitions, $registrar);

        $this->registered = $discovered;
    }

    /** @return list<Diagnostic|string> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function registry(): ?DiscoveredRoutes
    {
        return $this->registry;
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
        $basePath = base_path();

        if (str_starts_with($path, $basePath)) {
            return trim(str_replace($basePath, '', $path), DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    /**
     * @param  list<RouteDefinition>  $definitions
     */
    private function buildRegistry(array $definitions, RouteRegistrar $registrar): void
    {
        $entries = [];
        $discarded = $registrar->discardedDefinitions();
        $discardedIds = [];

        foreach ($discarded as $def) {
            $discardedIds[hash('xxh32', "{$def->group}\0{$def->fullyQualifiedClassName}\0{$def->method->getName()}")] = $def;
        }

        foreach ($definitions as $def) {
            $groupId = $def->group;

            if ($def->invalidReason !== null) {
                $entries[] = DiscoveredRouteEntry::fromRouteDefinition(
                    def: $def,
                    status: RouteStatus::Invalid,
                    group: $groupId,
                );

                continue;
            }

            $entryId = hash('xxh32', "{$groupId}\0{$def->fullyQualifiedClassName}\0{$def->method->getName()}");

            if (isset($discardedIds[$entryId])) {
                $entries[] = DiscoveredRouteEntry::fromRouteDefinition(
                    def: $def,
                    status: RouteStatus::Discarded,
                    group: $groupId,
                    discardReason: 'Duplicate route removed during registration.',
                );

                continue;
            }

            if (! $def->isDiscoverable && $def->skipReason !== null) {
                $entries[] = DiscoveredRouteEntry::fromRouteDefinition(
                    def: $def,
                    status: RouteStatus::Skipped,
                    group: $groupId,
                );

                continue;
            }

            if ($def->isDiscoverable || $def->isFallbackVerb) {
                $entries[] = DiscoveredRouteEntry::fromRouteDefinition(
                    def: $def,
                    status: RouteStatus::Registered,
                    group: $groupId,
                );
            }
        }

        $diagnostics = [];
        foreach ($this->diagnostics as $diag) {
            if ($diag instanceof Diagnostic) {
                $diagnostics[] = $diag;
            } else {
                $diagnostics[] = new Diagnostic(
                    code: 'discovery_generic',
                    severity: 'info',
                    group: '',
                    path: '',
                    message: (string) $diag,
                );
            }
        }

        $this->registry = new DiscoveredRoutes($entries, $diagnostics);
    }
}
