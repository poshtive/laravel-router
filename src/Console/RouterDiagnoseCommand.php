<?php

namespace Poshtive\Router\Console;

use Illuminate\Console\Command;

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

        foreach ($groups as $name => $group) {
            $this->line(sprintf('- %s: %d path(s)', $name, count((array) ($group['paths'] ?? []))));
        }

        return self::SUCCESS;
    }
}
