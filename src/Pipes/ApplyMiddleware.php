<?php

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\IgnoreParentMiddleware;
use Poshtive\Router\Attributes\Route as RouteAttribute;

class ApplyMiddleware
{
    public function handle(array $definitions, Closure $next)
    {
        foreach ($definitions as $definition) {
            $parentMiddleware = [];

            $ignoreOnClass = ! empty($definition->class->getAttributes(IgnoreParentMiddleware::class));
            $ignoreOnMethod = ! empty($definition->method->getAttributes(IgnoreParentMiddleware::class));

            if (! $ignoreOnClass && ! $ignoreOnMethod) {
                foreach ($definition->parentAttributes as $attribute) {
                    if ($attribute instanceof RouteAttribute && $attribute->middleware) {
                        $parentMiddleware = array_merge($parentMiddleware, (array) $attribute->middleware);
                    }
                }
            }

            $classMiddleware = [];
            $classRouteAttr = $definition->class->getAttributes(RouteAttribute::class);
            if (! $ignoreOnMethod && ! empty($classRouteAttr)) {
                $classMiddleware = (array) $classRouteAttr[0]->newInstance()->middleware;
            }

            $methodMiddleware = [];
            $methodRouteAttr = $definition->method->getAttributes(RouteAttribute::class);
            if (! empty($methodRouteAttr)) {
                $methodMiddleware = (array) $methodRouteAttr[0]->newInstance()->middleware;
            }

            $definition->middleware = array_unique(array_merge($parentMiddleware, $classMiddleware, $methodMiddleware));
        }

        return $next($definitions);
    }
}
