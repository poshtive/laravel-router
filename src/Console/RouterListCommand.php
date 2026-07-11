<?php

namespace Poshtive\Router\Console;

use Illuminate\Console\Command;

class RouterListCommand extends Command
{
    protected $signature = 'router:list {--path= : Only show routes containing this path}';

    protected $description = 'List routes discovered by Laravel Router';

    public function handle(): int
    {
        $path = $this->option('path');
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $path === null || str_contains('/'.$route->uri(), $path));

        $this->table(['Method', 'URI', 'Name', 'Action'], $routes->map(fn ($route) => [
            implode('|', $route->methods()),
            $route->uri(),
            $route->getName() ?? '',
            is_string($route->getActionName()) ? $route->getActionName() : 'Closure',
        ])->all());

        return self::SUCCESS;
    }
}
