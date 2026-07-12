<?php

declare(strict_types=1);

namespace Poshtive\Router\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;

class RouterListCommand extends Command
{
    protected $signature = 'router:list {--path= : Only show routes containing this path}';

    protected $description = 'List routes discovered by Laravel Router';

    public function handle(): int
    {
        $path = $this->option('path');
        $routes = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn (Route $route): bool => $path === null || str_contains('/'.$route->uri(), $path),
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
