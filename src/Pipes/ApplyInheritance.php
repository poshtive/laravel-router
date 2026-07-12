<?php

declare(strict_types=1);

namespace Poshtive\Router\Pipes;

use Closure;
use Illuminate\Routing\Controller;
use Poshtive\Router\Attributes\DiscoveryAttribute;
use Poshtive\Router\RouteDefinition;
use ReflectionAttribute;
use ReflectionClass;

class ApplyInheritance
{
    /** @param list<RouteDefinition> $definitions */
    public function handle(array $definitions, Closure $next): mixed
    {
        foreach ($definitions as $definition) {
            $parentClasses = class_parents($definition->class->getName());
            $attributes = [];

            foreach ($parentClasses as $parent) {
                if ($parent === Controller::class) {
                    break;
                }

                $parentReflection = new ReflectionClass($parent);
                foreach ($parentReflection->getAttributes(DiscoveryAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    $attributes[] = $attribute->newInstance();
                }
            }
            $definition->parentAttributes = array_reverse($attributes);
        }

        return $next($definitions);
    }
}
