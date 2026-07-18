<?php

declare(strict_types=1);

namespace Poshtive\Router;

use Composer\InstalledVersions;
use Illuminate\Support\ServiceProvider;
use Poshtive\Router\Console\RouterDiagnoseCommand;
use Poshtive\Router\Console\RouterListCommand;
use Poshtive\Router\Discovery\BuildFingerprint;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\DiscoveredRouteEntry;
use Poshtive\Router\Discovery\DiscoveredRoutes;
use Poshtive\Router\Discovery\DiscoveryManifest;
use Poshtive\Router\Discovery\ManifestCacheManager;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Discovery\RouteStatus;

class RouterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/router.php' => \config_path('router.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([RouterListCommand::class, RouterDiagnoseCommand::class]);
        }

        if ($this->app->bound('router') && config('router.enabled', true)) {
            $manager = $this->app->make(RouteDiscoveryManager::class);
            $manager->discover((array) config('router.groups', []));
            $this->exposeRegistry($manager);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/router.php',
            'router'
        );

        $this->app->singleton(RouteDiscoveryManager::class, fn ($app) => new RouteDiscoveryManager($app->make('router')));
    }

    private function exposeRegistry(RouteDiscoveryManager $manager): void
    {
        $registry = $manager->registry();

        if ($registry !== null && $registry->count() > 0) {
            $this->app->instance(DiscoveredRoutes::class, $registry);

            return;
        }

        if ($this->app->routesAreCached()) {
            $cacheManager = new ManifestCacheManager(base_path());
            $manifest = $cacheManager->read();

            if ($manifest !== null) {
                $entries = array_map(
                    fn (array $data): DiscoveredRouteEntry => new DiscoveredRouteEntry(
                        id: (string) ($data['id'] ?? ''),
                        group: (string) ($data['group'] ?? ''),
                        status: RouteStatus::from((string) ($data['status'] ?? 'registered')),
                        methods: (array) ($data['methods'] ?? []),
                        uri: (string) ($data['uri'] ?? ''),
                        name: (string) ($data['name'] ?? ''),
                        domain: isset($data['domain']) ? (string) $data['domain'] : null,
                        controller: (string) ($data['controller'] ?? ''),
                        method: (string) ($data['method'] ?? ''),
                        sourceFile: (string) ($data['source_file'] ?? ''),
                        sourceLine: isset($data['source_line']) ? (int) $data['source_line'] : null,
                        middleware: (array) ($data['middleware'] ?? []),
                        wheres: (array) ($data['wheres'] ?? []),
                        scopeBindings: (bool) ($data['scope_bindings'] ?? false),
                        withoutScopedBindings: (bool) ($data['without_scoped_bindings'] ?? false),
                        skipReason: isset($data['skip_reason']) ? (string) $data['skip_reason'] : null,
                        invalidReason: isset($data['invalid_reason']) ? (string) $data['invalid_reason'] : null,
                        discardReason: isset($data['discard_reason']) ? (string) $data['discard_reason'] : null,
                        provenance: (array) ($data['provenance'] ?? []),
                    ),
                    $manifest->entries,
                );

                $diagnostics = array_map(
                    fn (array $data): Diagnostic => new Diagnostic(
                        code: (string) ($data['code'] ?? 'unknown'),
                        severity: (string) ($data['severity'] ?? 'info'),
                        group: (string) ($data['group'] ?? ''),
                        path: (string) ($data['path'] ?? ''),
                        message: (string) ($data['message'] ?? ''),
                    ),
                    $manifest->diagnostics,
                );

                $this->app->instance(DiscoveredRoutes::class, new DiscoveredRoutes($entries, $diagnostics));

                return;
            }

            $this->app->instance(DiscoveredRoutes::class, new DiscoveredRoutes([], []));

            return;
        }

        $this->app->instance(DiscoveredRoutes::class, new DiscoveredRoutes([], []));
    }

    /**
     * @internal Called by test infrastructure and route:cache lifecycle.
     */
    public static function writeManifest(): void
    {
        $app = app();

        if (! $app->bound(DiscoveredRoutes::class)) {
            return;
        }

        $registry = $app->make(DiscoveredRoutes::class);
        $entries = $registry->all();
        $diags = $registry->diagnostics();

        $registeredCount = count($registry->routes());
        $packageVersion = InstalledVersions::getPrettyVersion('poshtive/router') ?? 'unknown';

        $fingerprint = BuildFingerprint::generate($entries, $packageVersion);

        $manifest = new DiscoveryManifest(
            schemaVersion: DiscoveryManifest::SCHEMA_VERSION,
            buildFingerprint: $fingerprint,
            entries: array_map(fn ($e) => $e->toArray(), $entries),
            diagnostics: array_map(fn ($d) => $d->toArray(), $diags),
            totalRoutes: $registeredCount,
            hasZeroRoutes: $registeredCount === 0,
            packageVersion: $packageVersion,
        );

        $cacheManager = new ManifestCacheManager(base_path());
        $cacheManager->write($manifest);
    }

    /**
     * @internal Called by test infrastructure and route:clear lifecycle.
     */
    public static function removeManifest(): void
    {
        $cacheManager = new ManifestCacheManager(base_path());
        $cacheManager->remove();
    }
}
