<?php

declare(strict_types=1);

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Except;
use Poshtive\Router\Attributes\LocalOnly;
use Poshtive\Router\Attributes\Only;
use Poshtive\Router\RouteDefinition;

class FilterRoutes
{
    /** @param list<RouteDefinition> $definitions */
    public function handle(array $definitions, Closure $next): mixed
    {
        foreach ($definitions as $definition) {
            $reason = $this->skipReason($definition);

            if ($reason !== null) {
                $definition->markSkipped(sprintf('Skipped %s because %s.', $definition->descriptor(), $reason));
            }
        }

        return $next($definitions);
    }

    private function skipReason(RouteDefinition $definition): ?string
    {
        if ($definition->hasClassAttribute(DoNotDiscover::class)) {
            return sprintf('[%s] is marked with #[DoNotDiscover]', $definition->class->getName());
        }

        if ($definition->hasMethodAttribute(DoNotDiscover::class)) {
            return 'the method is marked with #[DoNotDiscover]';
        }

        $only = $definition->classAttributeInstances(Only::class)[0] ?? null;
        $except = $definition->classAttributeInstances(Except::class)[0] ?? null;
        $method = $definition->method->getName();

        if ($only instanceof Only && $except instanceof Except) {
            $definition->markInvalid(sprintf(
                '[%s] cannot use #[Only] and #[Except] together.',
                $definition->class->getName(),
            ));

            return null;
        }

        if ($only instanceof Only && ! in_array($method, $only->methods, true)) {
            return 'the method is not listed in #[Only]';
        }

        if ($except instanceof Except && in_array($method, $except->methods, true)) {
            return 'the method is listed in #[Except]';
        }

        $localOnly = array_merge(
            $definition->parentAttributes,
            $definition->classAttributeInstances(LocalOnly::class),
            $definition->methodAttributeInstances(LocalOnly::class),
        );

        foreach ($localOnly as $attribute) {
            if ($attribute instanceof LocalOnly && ! \app()->isLocal()) {
                return '#[LocalOnly] routes are only registered in the local environment';
            }
        }

        return null;
    }
}
