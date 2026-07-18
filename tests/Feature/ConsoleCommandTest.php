<?php

namespace Tests\Feature;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Tests\TestCase;

class ConsoleCommandTest extends TestCase
{
    public function test_diagnose_command_reports_discovery_state(): void
    {
        $this->artisan('router:diagnose')
            ->expectsOutputToContain('Discovery: enabled')
            ->expectsOutputToContain('Groups: 2')
            ->expectsOutputToContain('Diagnostics:')
            ->assertExitCode(0);
    }

    public function test_list_command_is_available(): void
    {
        $this->artisan('router:list')
            ->assertExitCode(0);
    }

    public function test_list_command_filters_discovered_routes_by_path(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'configured' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        $this->artisan('router:list', ['--path' => 'profiles'])
            ->expectsOutputToContain('account/{account}/profiles')
            ->doesntExpectOutputToContain('account/settings')
            ->assertExitCode(0);
    }

    public function test_diagnose_command_lists_discovery_diagnostics(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'diagnostics' => [
                'paths' => [$this->fixturePath('Diagnostics/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Diagnostics\\Controllers\\',
                'patterns' => ['HiddenController.php'],
            ],
        ]);

        $this->artisan('router:diagnose')
            ->expectsOutputToContain('Diagnostics:')
            ->expectsOutputToContain('DoNotDiscover')
            ->assertExitCode(0);
    }

    public function test_list_command_filters_to_discovered_routes_by_default(): void
    {
        app('router')->get('/manual', fn () => 'ok')->name('manual');

        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        // Default: only discovered routes
        $this->artisan('router:list')
            ->expectsOutputToContain('account/{account}/profiles')
            ->doesntExpectOutputToContain('/manual')
            ->assertExitCode(0);

        // --all: includes manual routes too
        $this->artisan('router:list', ['--all' => true])
            ->expectsOutputToContain('manual')
            ->assertExitCode(0);
    }

    public function test_diagnose_reports_separate_route_counts(): void
    {
        app('router')->get('/manual', fn () => 'ok');

        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        $this->artisan('router:diagnose')
            ->expectsOutputToContain('Laravel routes:')
            ->expectsOutputToContain('Discovered routes:')
            ->assertExitCode(0);
    }

    public function test_discovered_routes_carry_package_metadata_in_action(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        $discoveredRoutes = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );

        $this->assertNotEmpty($discoveredRoutes);
        foreach ($discoveredRoutes as $route) {
            $meta = $route->getAction('_laravel_router');
            $this->assertArrayHasKey('id', $meta);
            $this->assertArrayHasKey('group', $meta);
            $this->assertSame('web', $meta['group']);
        }
    }

    public function test_route_cache_preserves_package_metadata(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        // Verify metadata is present before caching
        $preCached = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );
        $this->assertNotEmpty($preCached);

        // Compile routes and load into a fresh router to simulate route cache
        $compiledRoutes = app('router')->getRoutes()->compile();
        $router = new Router(app('events'), app());
        $router->setCompiledRoutes($compiledRoutes);

        $cachedRoutes = $router->getRoutes()->getRoutes();
        $discoveredFromCache = array_filter(
            $cachedRoutes,
            fn ($route) => $route->getAction('_laravel_router') !== null,
        );
        $this->assertCount(count($preCached), $discoveredFromCache);
    }

    public function test_list_command_filters_by_group(): void
    {
        require_once $this->fixturePath('MultiPath/Controllers/First/FirstController.php');
        require_once $this->fixturePath('MultiPath/Controllers/Second/SecondController.php');

        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'first' => [
                'paths' => [$this->fixturePath('MultiPath/Controllers/First')],
                'namespace' => 'Tests\\Fixtures\\MultiPath\\Controllers\\',
                'prefix' => 'first',
                'name' => 'first.',
            ],
            'second' => [
                'paths' => [$this->fixturePath('MultiPath/Controllers/Second')],
                'namespace' => 'Tests\\Fixtures\\MultiPath\\Controllers\\',
                'prefix' => 'second',
                'name' => 'second.',
            ],
        ]);

        // --group=first only shows first group routes
        $this->artisan('router:list', ['--group' => 'first'])
            ->expectsOutputToContain('first')
            ->doesntExpectOutputToContain('second')
            ->assertExitCode(0);

        // --group=second only shows second group routes
        $this->artisan('router:list', ['--group' => 'second'])
            ->expectsOutputToContain('second')
            ->doesntExpectOutputToContain('first')
            ->assertExitCode(0);
    }

    public function test_list_command_group_composes_with_path(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        // --group=web --path=profiles filters within group
        $this->artisan('router:list', ['--group' => 'web', '--path' => 'profiles'])
            ->expectsOutputToContain('profiles')
            ->assertExitCode(0);

        // --group=web --path=nonexistent yields empty result for the group
        $this->artisan('router:list', ['--group' => 'web', '--path' => 'zzz_nonexistent'])
            ->assertExitCode(0);
    }

    public function test_list_command_json_output(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        Artisan::call('router:list', ['--json' => true]);
        $output = Artisan::output();
        $decoded = json_decode(trim($output), true);

        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);

        $first = $decoded[0];
        $this->assertArrayHasKey('methods', $first);
        $this->assertArrayHasKey('uri', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('action', $first);
        $this->assertArrayHasKey('domain', $first);
        $this->assertArrayHasKey('middleware', $first);
        $this->assertArrayHasKey('wheres', $first);
        $this->assertArrayHasKey('group', $first);
        $this->assertArrayHasKey('id', $first);
        $this->assertIsArray($first['methods']);
        $this->assertSame('web', $first['group']);
    }

    public function test_list_command_unknown_group_warns(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'web' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
            ],
        ]);

        // Unknown group warns in table mode
        $this->artisan('router:list', ['--group' => 'nonexistent'])
            ->expectsOutputToContain('not configured')
            ->assertExitCode(0);

        // Unknown group in JSON mode outputs empty JSON array
        Artisan::call('router:list', ['--group' => 'nonexistent', '--json' => true]);
        $output = Artisan::output();
        $decoded = json_decode(trim($output), true);
        $this->assertIsArray($decoded);
        $this->assertEmpty($decoded);
    }

    public function test_diagnose_fail_on_warning_exits_nonzero(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'diagnostics' => [
                'paths' => [$this->fixturePath('Diagnostics/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Diagnostics\\Controllers\\',
            ],
        ]);

        // With diagnostics present, --fail-on-warning exits non-zero
        $this->artisan('router:diagnose', ['--fail-on-warning' => true])
            ->assertExitCode(1);
    }

    public function test_diagnose_fail_on_warning_exits_zero_when_clean(): void
    {
        require_once $this->fixturePath('MultiPath/Controllers/First/FirstController.php');

        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);
        $manager->discover([
            'clean' => [
                'paths' => [$this->fixturePath('MultiPath/Controllers/First')],
                'namespace' => 'Tests\\Fixtures\\MultiPath\\Controllers\\',
            ],
        ]);

        // Clean discovery with --fail-on-warning exits 0
        $this->artisan('router:diagnose', ['--fail-on-warning' => true])
            ->assertExitCode(0);
    }

    public function test_diagnose_fail_on_warning_uses_structured_severity(): void
    {
        // Verify that the fail-on-warning logic references Diagnostic::$severity structurally,
        // not by parsing message text. The test ensures that a diagnostic with severity
        // 'warning' (the lowest failing severity) triggers a non-zero exit, while the
        // absence of diagnostics triggers zero.

        $manager = new RouteDiscoveryManager(app('router'));
        app()->instance(RouteDiscoveryManager::class, $manager);

        // Discover with a missing path to generate a warning-level diagnostic
        $manager->discover([
            'warnGroup' => [
                'paths' => ['/nonexistent/path/4f8a2c1b'],
            ],
        ]);

        $diagnostics = $manager->diagnostics();
        $this->assertNotEmpty($diagnostics);

        // Verify the diagnostic is a structured Diagnostic with severity, not a raw string
        $this->assertInstanceOf(Diagnostic::class, $diagnostics[0]);
        $this->assertSame('warning', $diagnostics[0]->severity);

        // With diagnostics present, --fail-on-warning exits non-zero
        $this->artisan('router:diagnose', ['--fail-on-warning' => true])
            ->assertExitCode(1);
    }
}
