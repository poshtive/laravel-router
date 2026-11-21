<?php

declare(strict_types=1);

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\IgnoreParentMiddleware;
use Poshtive\Router\Attributes\Middleware;
use Poshtive\Router\RouteDefinition;

class ApplyMiddleware
{
    /** @param list<RouteDefinition> $definitions */
    public function handle(array $definitions, Closure $next): mixed
    {
        foreach ($definitions as $definition) {
            $ignoreOnClass = $definition->hasClassAttribute(IgnoreParentMiddleware::class);
            $ignoreOnMethod = $definition->hasMethodAttribute(IgnoreParentMiddleware::class);

            $middleware = [];

            if (! $ignoreOnClass && ! $ignoreOnMethod) {
                $middleware[] = $this->flatten($definition->parentAttributes);
            }

            if (! $ignoreOnMethod) {
                $middleware[] = $this->flatten($definition->classAttributeInstances(Middleware::class));
            }

            $middleware[] = $this->flatten($definition->methodAttributeInstances(Middleware::class));

            $definition->middleware = array_values(array_unique(array_merge(...$middleware)));
        }

        return $next($definitions);
    }

    /**
     * @param  list<object>  $attributes
     * @return list<string>
     */
    private function flatten(array $attributes): array
    {
        $middleware = [];

        foreach ($attributes as $attribute) {
            if ($attribute instanceof Middleware) {
                $middleware = array_merge($middleware, $attribute->middleware);
            }
        }

        return $middleware;
    }
}
