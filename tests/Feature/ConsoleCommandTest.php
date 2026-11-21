<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Tests\TestCase;

class ConsoleCommandTest extends TestCase
{
    public function test_check_passes_for_valid_discovery(): void
    {
        config()->set('router.groups', [
            'web' => [
                'paths' => [$this->fixturePath('Resource/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Resource\\Controllers',
            ],
        ]);

        $this->artisan('router:check')
            ->expectsOutputToContain('Discovered 8 route(s) in 1 group(s).')
            ->expectsOutputToContain('Route discovery check passed.')
            ->assertExitCode(0);
    }

    public function test_check_reports_skipped_routes_as_info_without_failing(): void
    {
        config()->set('router.groups', [
            'diagnostics' => [
                'paths' => [$this->fixturePath('Diagnostics/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Diagnostics\\Controllers',
                'patterns' => ['HiddenController.php'],
            ],
        ]);

        $this->artisan('router:check')
            ->expectsOutputToContain('route_skipped')
            ->expectsOutputToContain('Route discovery check passed.')
            ->assertExitCode(0);
    }

    public function test_check_fails_on_warnings_and_errors(): void
    {
        config()->set('router.groups', [
            'missing' => ['paths' => ['/nonexistent/path/4f8a2c1b']],
            'invalid' => [
                'paths' => [$this->fixturePath('Invalid/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Invalid\\Controllers',
            ],
        ]);

        $this->artisan('router:check')
            ->expectsOutputToContain('discovery_path_missing')
            ->expectsOutputToContain('route_invalid')
            ->expectsOutputToContain('Route discovery check failed with 2 warning(s) or error(s).')
            ->assertExitCode(1);
    }

    public function test_check_warns_about_unknown_only_and_except_actions(): void
    {
        config()->set('router.groups', [
            'web' => [
                'paths' => [$this->fixturePath('ActionFilterTypo/Controllers')],
                'namespace' => 'Tests\\Fixtures\\ActionFilterTypo\\Controllers',
            ],
        ]);

        Artisan::call('router:check', ['--json' => true]);
        $result = json_decode(Artisan::output(), true);

        $this->assertFalse($result['passed']);
        $this->assertSame(2, $result['routes']);
        $this->assertSame('action_filter_unknown', $result['diagnostics'][0]['code']);
        $this->assertSame('warning', $result['diagnostics'][0]['severity']);
        $this->assertStringContainsString('lists [destory]', $result['diagnostics'][0]['message']);
    }

    public function test_check_runs_with_cached_routes_and_leaves_the_application_router_alone(): void
    {
        config()->set('router.groups', [
            'web' => [
                'paths' => [$this->fixturePath('Resource/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Resource\\Controllers',
            ],
        ]);
        app()->instance('routes.cached', true);
        $before = count(app('router')->getRoutes()->getRoutes());

        Artisan::call('router:check', ['--json' => true]);

        $this->assertSame(8, json_decode(Artisan::output(), true)['routes']);
        $this->assertCount($before, app('router')->getRoutes()->getRoutes());
    }

    public function test_discovered_routes_carry_package_metadata_in_action(): void
    {
        $manager = new RouteDiscoveryManager(app('router'));
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
            $this->assertSame(['group' => 'web'], $route->getAction('_laravel_router'));
        }
    }
}
