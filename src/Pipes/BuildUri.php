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
        $modified = [];
        $method = $definition->getMethodName();
        $bindings = [];
        foreach ($definition->method->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType) {
                if ($type->isBuiltin() || is_subclass_of($type->getName(), Model::class) || enum_exists($type->getName())) {
                    $bindings[] = $param->getName();
                }
            }
        }
        $binding_count = count($bindings);
        foreach ($parts as $part) {
            if (str_starts_with($part, '{')) {
                if (empty($bindings)) {
                    throw new RuntimeException("Not enough parameters to bind for {$definition->method->getName()} in {$definition->fullyQualifiedClassName}");
                }
                $pop = array_shift($bindings);
                $part = '{'.$pop.'}';
                $binding_count--;
            }
            $prev = $part;
            if ($part === 'index') {
                $prev = '';
            }
            $modified[] = $prev;
        }
        if (! $definition->keepOrder && $binding_count > 0) {
            $modified[] = '{'.array_shift($bindings).'}';
            $binding_count--;
        }
        $method = Str::kebab($method);
        if ($definition->methodUri !== null) {
            if ($method === 'index') {
                $modified[count($modified) - 1] = trim($definition->methodUri, '/');
            } else {
                $modified[] = trim($definition->methodUri, '/');
            }
        } elseif ($method !== 'index') {
            $modified[] = $method;
        }
        if ($binding_count > 0) {
            foreach ($bindings as $binding) {
                $modified[] = '{'.$binding.'}';
            }
        }

        return $modified;
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
