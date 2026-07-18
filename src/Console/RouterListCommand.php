<?php

declare(strict_types=1);

namespace Poshtive\Router\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;

class RouterListCommand extends Command
{
    protected $signature = 'router:list {--path= : Only show routes containing this path} {--all : Include all Laravel routes, not just discovered ones}';

    protected $description = 'List routes discovered by Laravel Router';

    public function handle(): int
    {
        $path = $this->option('path');
        $all = $this->option('all');
        $routes = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn (Route $route): bool => ($all || $route->getAction('_laravel_router') !== null)
                && ($path === null || str_contains('/'.$route->uri(), $path)),
        );

        $this->table(['Method', 'URI', 'Name', 'Action'], array_map(fn (Route $route): array => [
            implode('|', $route->methods()),
            $route->uri(),
            $route->getName() ?? '',
            $route->getActionName(),
        ], $routes));

        return self::SUCCESS;
    }
}
