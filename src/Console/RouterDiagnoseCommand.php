<?php

declare(strict_types=1);

namespace Poshtive\Router\Console;

use Illuminate\Console\Command;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Discovery\Severity;

class RouterDiagnoseCommand extends Command
{
    protected $signature = 'router:diagnose
                            {--fail-on-warning : Exit non-zero when diagnostics contain warnings or errors}';

    protected $description = 'Show Laravel Router discovery configuration and route totals';

    public function handle(): int
    {
        $groups = (array) config('router.groups', []);
        $allRoutes = app('router')->getRoutes()->getRoutes();
        $totalRoutes = count($allRoutes);
        $discoveredCount = count(array_filter($allRoutes, fn ($route) => $route->getAction('_laravel_router') !== null));

        $this->line('Discovery: '.(config('router.enabled', true) ? 'enabled' : 'disabled'));
        $this->line('Groups: '.count($groups));
        $this->line('Laravel routes: '.$totalRoutes);
        $this->line('Discovered routes: '.$discoveredCount);

        $diagnostics = app(RouteDiscoveryManager::class)->diagnostics();
        $this->line('Diagnostics: '.count($diagnostics));

        foreach ($groups as $name => $group) {
            $this->line(sprintf('- %s: %d path(s)', $name, count((array) ($group['paths'] ?? []))));
        }

        foreach ($diagnostics as $diagnostic) {
            $this->line(sprintf(
                '  [%s] %s (%s): %s',
                $diagnostic->severity->value,
                $diagnostic->group,
                $diagnostic->path,
                $diagnostic->message,
            ));
        }

        $failing = array_filter($diagnostics, fn (Diagnostic $diagnostic): bool => $diagnostic->severity !== Severity::Info);

        return $this->option('fail-on-warning') && $failing !== [] ? self::FAILURE : self::SUCCESS;
    }
}
