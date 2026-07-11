<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConsoleCommandTest extends TestCase
{
    public function test_diagnose_command_reports_discovery_state(): void
    {
        $this->artisan('router:diagnose')
            ->expectsOutputToContain('Discovery: enabled')
            ->expectsOutputToContain('Groups: 2')
            ->assertExitCode(0);
    }

    public function test_list_command_is_available(): void
    {
        $this->artisan('router:list')
            ->assertExitCode(0);
    }
}
