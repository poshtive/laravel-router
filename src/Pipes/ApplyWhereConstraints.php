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
                array_map(fn ($a) => $a->newInstance(), $definition->class->getAttributes(Where::class, ReflectionAttribute::IS_INSTANCEOF)),
                array_map(fn ($a) => $a->newInstance(), $definition->method->getAttributes(Where::class, ReflectionAttribute::IS_INSTANCEOF))
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
