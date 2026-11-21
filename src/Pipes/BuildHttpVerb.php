<?php

declare(strict_types=1);

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\RouteDefinition;
use Poshtive\Router\Support\ResourceActions;

class BuildHttpVerb
{
    /** @param list<RouteDefinition> $definitions */
    public function handle(array $definitions, Closure $next): mixed
    {
        foreach ($definitions as $definition) {
            if (! $definition->isDiscoverable || ! empty($definition->httpVerb)) {
                continue;
            }

            $definition->httpVerb = ResourceActions::verbsFor($definition->method->getName());
        }

        return $next($definitions);
    }
}
