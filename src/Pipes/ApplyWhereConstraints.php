<?php

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\Where;
use ReflectionAttribute;

class ApplyWhereConstraints
{
    public function handle(array $definitions, Closure $next)
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
