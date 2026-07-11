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

    public function test_diagnose_command_lists_discovery_diagnostics(): void
    {
        app(RouteDiscoveryManager::class)->discover([
            'diagnostics' => [
                'paths' => [$this->fixturePath('Diagnostics/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Diagnostics\\Controllers\\',
            ],
        ]);

        $this->artisan('router:diagnose')
            ->expectsOutputToContain('Diagnostics:')
            ->expectsOutputToContain('DoNotDiscover')
            ->assertExitCode(0);
    }
}
