<?php

namespace Poshtive\Router\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Poshtive\Router\RouteDefinition;
use ReflectionNamedType;
use RuntimeException;

class BuildUri
{
    public function handle(array $definitions, Closure $next)
    {
        foreach ($definitions as $definition) {
            if (! $definition->isDiscoverable) {
                continue;
            }
            if (! empty($definition->uri) && $definition->methodUri === null && $definition->classUri === null) {
                continue;
            }

            $definition->uri = $this->buildUri($definition);
        }

        return $next($definitions);
    }

    private function buildUri(RouteDefinition $definition): string
    {
        if ($definition->absolute && $definition->methodUri !== null) {
            return trim($definition->methodUri, '/');
        }

        $parts = $this->handleNestedFolder($definition);
        if ($definition->classUri !== null) {
            $parts[count($parts) - 1] = trim($definition->classUri, '/');
        }
        $parts = $this->handleCase($parts);
        $parts = $this->handleParameters($parts, $definition);
        if (count($parts) > 1) {
            $parts = array_filter($parts);
        }

        return implode('/', $parts);
    }

    private function handleParameters(array $parts, RouteDefinition $definition): array
    {
        $bindings = [];
        foreach ($definition->method->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType) {
                if ($type->isBuiltin() || is_subclass_of($type->getName(), Model::class) || enum_exists($type->getName())) {
                    $bindings[] = [
                        'name' => $param->getName(),
                        'optional' => $param->isOptional() || $type->allowsNull(),
                    ];
                }
            }
        }
        $parts = array_values(array_filter(array_merge(...array_map(
            fn (string $part) => explode('/', trim($part, '/')),
            $parts,
        )), fn (string $part) => $part !== ''));
        $parts = $this->handleCase($parts);
        $modified = [];
        foreach ($parts as $part) {
            $modified[] = $this->resolveBindings($part, $bindings, $definition);
        }

        $method = $definition->getMethodName();
        $methodParts = $definition->methodUri !== null
            ? explode('/', trim($definition->methodUri, '/'))
            : ($method === 'index' ? [] : [Str::kebab($method)]);
        $methodHasPlaceholder = $definition->methodUri !== null && (bool) preg_match('/\{[^}]+\}/', $definition->methodUri);

        if ($definition->methodUri !== null && $method === 'index') {
            array_pop($modified);
        }

        if (! $definition->keepOrder && ! $methodHasPlaceholder && $bindings !== [] && ! $bindings[0]['optional']) {
            $modified[] = $this->formatBinding(array_shift($bindings));
        }

        foreach ($methodParts as $methodPart) {
            $modified[] = $this->resolveBindings($methodPart, $bindings, $definition, false);
        }

        foreach ($bindings as $binding) {
            $modified[] = $this->formatBinding($binding);
        }

        return $modified;
    }

    private function resolveBindings(string $part, array &$bindings, RouteDefinition $definition, bool $normalize = true): string
    {
        if (! preg_match('/\{([^}]+)\}/', $part, $match)) {
            if ($part === 'index') {
                return '';
            }

            return $normalize ? Str::kebab(Str::studly($part)) : $part;
        }

        if ($bindings === []) {
            throw new RuntimeException("Not enough parameters to bind for {$definition->method->getName()} in {$definition->fullyQualifiedClassName}");
        }

        $binding = array_shift($bindings);
        $placeholder = $match[1];
        $name = explode(':', rtrim($placeholder, '?'), 2)[0];
        $replacement = ctype_digit($name) ? $this->formatBinding($binding) : '{'.$placeholder.'}';

        return str_replace($match[0], $replacement, $part);
    }

    private function formatBinding(array $binding): string
    {
        return '{'.$binding['name'].($binding['optional'] ? '?' : '').'}';
    }

    private function handleCase(array $parts): array
    {
        return array_map(function ($part) {
            if (str_contains($part, '{')) {
                return $part;
            }

            return Str::kebab(Str::studly($part));
        }, $parts);
    }

    private function handleNestedFolder(RouteDefinition $definition): array
    {
        $parts = explode(DIRECTORY_SEPARATOR, str_replace('Controller.php', '', $definition->file->getRelativePathname()));
        $search = str_replace($definition->file->getRelativePathname(), '', $definition->file->getRealPath());
        $e = 1;
        for ($i = 0; $i < count($parts) - 1; $i++) {
            if (strtolower($parts[$i]) === 'index') {
                throw new RuntimeException("Index folder is not allowed in route discovery: {$definition->file->getRelativePathname()}");
            }
            if (file_exists($search.$parts[$i].'Controller.php')) {
                $parts[$i] .= sprintf(':{%d}', $e++);
            }
            $search .= $parts[$i].DIRECTORY_SEPARATOR;
        }
        $expanded = [];
        foreach ($parts as $part) {
            $expanded = array_merge($expanded, explode(':', $part));
        }

        return $expanded;
    }
}
