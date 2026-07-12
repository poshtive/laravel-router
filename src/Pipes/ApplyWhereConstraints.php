<?php

declare(strict_types=1);

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\Where;
use Poshtive\Router\RouteDefinition;
use ReflectionAttribute;

class ApplyWhereConstraints
{
    /** @param list<RouteDefinition> $definitions */
    public function handle(array $definitions, Closure $next): mixed
    {
        foreach ($definitions as $definition) {
            $allAttributes = array_merge(
                $definition->parentAttributes,
                $definition->classAttributeInstances(Where::class, ReflectionAttribute::IS_INSTANCEOF),
                $definition->methodAttributeInstances(Where::class, ReflectionAttribute::IS_INSTANCEOF)
            );

            $wheres = [];
            foreach ($allAttributes as $attribute) {
                if ($attribute instanceof Where) {
                    $wheres[$attribute->param] = $attribute->constraint;
                }
            }
            $definition->wheres = $wheres;
        }

        return $next($definitions);
    }
}
