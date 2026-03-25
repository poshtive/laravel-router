<?php

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\LocalOnly;

class FilterRoutes
{
    public function handle(array $definitions, Closure $next)
    {
        $filtered = array_filter($definitions, function ($def) {
            $classAttributes = $def->class->getAttributes(DoNotDiscover::class);
            if (!empty($classAttributes)) {
                $def->markSkipped(sprintf('Skipped %s because [%s] is marked with #[DoNotDiscover].', $def->descriptor(), $def->class->getName()));
                return true;
            }

            $allAttributes = array_merge(
                $def->parentAttributes,
                array_map(fn($a) => $a->newInstance(), $def->class->getAttributes(LocalOnly::class)),
                array_map(fn($a) => $a->newInstance(), $def->method->getAttributes(LocalOnly::class))
            );

            foreach ($allAttributes as $attribute) {
                if ($attribute instanceof LocalOnly && !\app()->isLocal()) {
                    $def->markSkipped(sprintf('Skipped %s because #[LocalOnly] routes are only registered in the local environment.', $def->descriptor()));
                    return true;
                }
            }

            return true;
        });

        return $next(array_values($filtered));
    }
}
