<?php

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\Route as RouteAttribute;

class ApplyRouteAttributes
{
    public function handle(array $definitions, Closure $next)
    {
        foreach ($definitions as $definition) {
            $classAttrInstance = $definition->classAttributeInstances(RouteAttribute::class)[0] ?? null;
            if ($classAttrInstance?->keepOrder) {
                $definition->keepOrder = true;
            }

            $methodAttrInstance = $definition->methodAttributeInstances(RouteAttribute::class)[0] ?? null;
            if ($methodAttrInstance?->uri !== null) {
                $definition->uri = $methodAttrInstance->uri;
            }

            if ($methodAttrInstance?->method !== null) {
                $method = is_string($methodAttrInstance->method)
                    ? [$methodAttrInstance->method]
                    : $methodAttrInstance->method;
                $definition->httpVerb = array_map('strtoupper', $method);
            }

            if ($methodAttrInstance?->keepOrder !== null) {
                $definition->keepOrder = $methodAttrInstance->keepOrder;
            }
        }

        return $next($definitions);
    }
}
