<?php

namespace Tests\Feature;

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
