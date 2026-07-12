<?php

declare(strict_types=1);

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\LocalOnly;
use Poshtive\Router\RouteDefinition;

class FilterRoutes
{
    /** @param list<RouteDefinition> $definitions */
    public function handle(array $definitions, Closure $next): mixed
    {
        $filtered = array_filter($definitions, function ($def) {
            if ($def->hasClassAttribute(DoNotDiscover::class)) {
                $def->markSkipped(sprintf('Skipped %s because [%s] is marked with #[DoNotDiscover].', $def->descriptor(), $def->class->getName()));

                return true;
            }

            if ($def->hasMethodAttribute(DoNotDiscover::class)) {
                $def->markSkipped(sprintf('Skipped %s because the method is marked with #[DoNotDiscover].', $def->descriptor()));

                return true;
            }

            $allAttributes = array_merge(
                $def->parentAttributes,
                $def->classAttributeInstances(LocalOnly::class),
                $def->methodAttributeInstances(LocalOnly::class)
            );

            foreach ($allAttributes as $attribute) {
                if ($attribute instanceof LocalOnly && ! \app()->isLocal()) {
                    $def->markSkipped(sprintf('Skipped %s because #[LocalOnly] routes are only registered in the local environment.', $def->descriptor()));

                    return true;
                }
            }

            return true;
        });

        return $next(array_values($filtered));
    }
}
