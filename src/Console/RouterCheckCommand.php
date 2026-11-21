<?php

declare(strict_types=1);

namespace Poshtive\Router\Console;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Routing\Router;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Discovery\Severity;

class RouterCheckCommand extends Command
{
    protected $signature = 'router:check
                            {--json : Output the result as JSON}';

    protected $description = 'Run route discovery and report problems; exits non-zero on warnings or errors';

    public function handle(): int
    {
        // Discover into a scratch router so the check works with cached routes
        // and never touches the application's route collection.
        $router = new Router($this->laravel->make('events'), Container::getInstance());
        $groups = (array) config('router.groups', []);
        $diagnostics = (new RouteDiscoveryManager($router))->inspect($groups);
        $routes = count($router->getRoutes()->getRoutes());

        $failing = array_values(array_filter(
            $diagnostics,
            fn (Diagnostic $diagnostic): bool => $diagnostic->severity !== Severity::Info,
        ));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'routes' => $routes,
                'groups' => count($groups),
                'passed' => $failing === [],
                'diagnostics' => $diagnostics,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($routes, count($groups), $diagnostics, count($failing));
        }

        return $failing === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<Diagnostic> $diagnostics */
    private function render(int $routes, int $groups, array $diagnostics, int $failing): void
    {
        $this->line(sprintf('Discovered %d route(s) in %d group(s).', $routes, $groups));

        if ($diagnostics !== []) {
            $this->table(['Severity', 'Code', 'Group', 'Where', 'Message'], array_map(fn (Diagnostic $diagnostic): array => [
                $diagnostic->severity->value,
                $diagnostic->code,
                $diagnostic->group,
                $diagnostic->action ?? $diagnostic->path,
                $diagnostic->message,
            ], $diagnostics));
        }

        if ($failing === 0) {
            $this->components->info('Route discovery check passed.');

            return;
        }

        $this->components->error(sprintf('Route discovery check failed with %d warning(s) or error(s).', $failing));
    }
}
