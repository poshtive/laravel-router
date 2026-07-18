<?php

declare(strict_types=1);

namespace Poshtive\Router\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;

class RouterListCommand extends Command
{
    protected $signature = 'router:list
                            {--path= : Only show routes containing this path}
                            {--all : Include all Laravel routes, not just discovered ones}
                            {--group= : Only show routes from this discovery group}
                            {--json : Output as JSON}';

    protected $description = 'List routes discovered by Laravel Router';

    public function handle(): int
    {
        $path = $this->option('path');
        $all = $this->option('all');
        $group = $this->option('group');
        $json = $this->option('json');

        if ($group !== null && ! array_key_exists($group, (array) config('router.groups', []))) {
            if ($json) {
                $this->line(json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->warn(sprintf('Discovery group [%s] is not configured.', $group));
            }

            return self::SUCCESS;
        }

        $routes = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn (Route $route): bool => ($all || $route->getAction('_laravel_router') !== null)
                && ($path === null || str_contains('/'.$route->uri(), $path))
                && ($group === null || ($route->getAction('_laravel_router')['group'] ?? null) === $group),
        );

        if ($json) {
            $this->line(json_encode(array_map(fn (Route $route): array => [
                'methods' => $route->methods(),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'domain' => $route->getDomain(),
                'middleware' => $route->gatherMiddleware(),
                'wheres' => $route->wheres,
                'group' => $route->getAction('_laravel_router')['group'] ?? null,
                'id' => $route->getAction('_laravel_router')['id'] ?? null,
            ], array_values($routes)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Method', 'URI', 'Name', 'Action'], array_map(fn (Route $route): array => [
            implode('|', $route->methods()),
            $route->uri(),
            $route->getName() ?? '',
            $route->getActionName(),
        ], $routes));

        return self::SUCCESS;
    }
}
