<?php

declare(strict_types=1);

namespace Poshtive\Router\Console;

use Illuminate\Console\Command;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\RouteDiscoveryManager;

class RouterDiagnoseCommand extends Command
{
    protected $signature = 'router:diagnose';

    protected $description = 'Show Laravel Router discovery configuration and route totals';

    public function handle(): int
    {
        $groups = (array) config('router.groups', []);
        $routeCount = count(app('router')->getRoutes()->getRoutes());

        $this->line('Discovery: '.(config('router.enabled', true) ? 'enabled' : 'disabled'));
        $this->line('Groups: '.count($groups));
        $this->line('Registered routes: '.$routeCount);

        $diagnostics = app(RouteDiscoveryManager::class)->diagnostics();
        $this->line('Diagnostics: '.count($diagnostics));

        foreach ($groups as $name => $group) {
            $this->line(sprintf('- %s: %d path(s)', $name, count((array) ($group['paths'] ?? []))));
        }

        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic instanceof Diagnostic) {
                $this->line(sprintf(
                    '  [%s] %s (%s): %s',
                    $diagnostic->severity,
                    $diagnostic->group,
                    $diagnostic->path,
                    $diagnostic->message,
                ));
            } else {
                $this->line('  * '.$diagnostic);
            }
        }

        return self::SUCCESS;
    }
}
