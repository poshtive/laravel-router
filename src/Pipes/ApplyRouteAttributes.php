<?php

declare(strict_types=1);

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\Attributes\HttpMethod;
use Poshtive\Router\Attributes\Resource;
use Poshtive\Router\Attributes\ScopeBindings;
use Poshtive\Router\Attributes\WithoutScopedBindings;
use Poshtive\Router\RouteDefinition;
use ReflectionAttribute;

class ApplyRouteAttributes
{
    /** @param list<RouteDefinition> $definitions */
    public function handle(array $definitions, Closure $next): mixed
    {
        foreach ($definitions as $definition) {
            $resource = $definition->classAttributeInstances(Resource::class)[0] ?? null;
            if ($resource instanceof Resource) {
                $definition->classUri = $resource->uri;
                $definition->className = $resource->name;
            }

            $this->applyHttpMethods($definition);
            $this->applyScopedBindings($definition);
        }

        return $next($definitions);
    }

    private function applyHttpMethods(RouteDefinition $definition): void
    {
        /** @var list<HttpMethod> $attributes */
        $attributes = $definition->methodAttributeInstances(HttpMethod::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attributes === []) {
            return;
        }

        $definition->httpVerb = array_values(array_unique(array_map(
            fn (HttpMethod $attribute): string => $attribute->verb(),
            $attributes,
        )));

        $uris = $this->declared(array_map(fn (HttpMethod $attribute): ?string => $attribute->uri, $attributes));
        $names = $this->declared(array_map(fn (HttpMethod $attribute): ?string => $attribute->name, $attributes));

        if (count($uris) > 1 || count($names) > 1) {
            $definition->markInvalid(sprintf(
                'HTTP method attributes on [%s] declare conflicting uri or name values.',
                $definition->descriptor(),
            ));

            return;
        }

        $definition->methodUri = $uris[0] ?? null;
        $definition->methodNameOverride = $names[0] ?? null;
    }

    private function applyScopedBindings(RouteDefinition $definition): void
    {
        $method = $this->scopedBindingsFor(
            $definition->hasMethodAttribute(ScopeBindings::class),
            $definition->hasMethodAttribute(WithoutScopedBindings::class),
            $definition,
        );

        $definition->scopeBindings = $method ?? $this->scopedBindingsFor(
            $definition->hasClassAttribute(ScopeBindings::class),
            $definition->hasClassAttribute(WithoutScopedBindings::class),
            $definition,
        );
    }

    private function scopedBindingsFor(bool $scope, bool $withoutScope, RouteDefinition $definition): ?bool
    {
        if ($scope && $withoutScope) {
            $definition->markInvalid(sprintf(
                '[%s] cannot use #[ScopeBindings] and #[WithoutScopedBindings] on the same target.',
                $definition->descriptor(),
            ));

            return null;
        }

        return match (true) {
            $scope => true,
            $withoutScope => false,
            default => null,
        };
    }

    /**
     * @param  list<?string>  $values
     * @return list<string>
     */
    private function declared(array $values): array
    {
        return array_values(array_unique(array_filter($values, fn (?string $value): bool => $value !== null)));
    }
}
