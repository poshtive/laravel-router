<?php

namespace Tests\Feature;

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
}
