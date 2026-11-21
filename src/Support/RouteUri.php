<?php

declare(strict_types=1);

namespace Poshtive\Router\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Build an action's URI from its controller path and method signature, and
 * check a finished URI before it is registered.
 *
 * @internal
 */
final class RouteUri
{
    private const string PLACEHOLDER = '/\{([^}]+)\}/';

    /** @var list<string> */
    private array $errors = [];

    /** @var list<array{name: string, optional: bool}> */
    private array $bindings = [];

    private function __construct(
        private readonly ReflectionMethod $method,
        private readonly string $descriptor,
    ) {}

    /**
     * @return array{string, list<string>} The URI without the group prefix, and why it cannot be built.
     */
    public static function build(ControllerPath $path, ?string $classUri, ?string $methodUri, ReflectionMethod $method, string $descriptor): array
    {
        if ($methodUri !== null && str_starts_with($methodUri, '/')) {
            return [trim($methodUri, '/'), []];
        }

        if ($classUri !== null && str_starts_with($classUri, '/')) {
            $controller = [trim($classUri, '/')];
        } elseif ($path->hasIndexFolder()) {
            return ['', [sprintf('Index folder is not allowed in route discovery: %s', $path->relative)]];
        } else {
            $controller = $path->uriSegments();
            if ($classUri !== null) {
                $controller[count($controller) - 1] = trim($classUri, '/');
            }
        }

        $uri = new self($method, $descriptor);

        return [$uri->assemble($controller, $methodUri), $uri->errors];
    }

    /** @return list<string> */
    public static function problems(string $uri, ReflectionMethod $method, string $descriptor): array
    {
        preg_match_all(self::PLACEHOLDER, $uri, $matches);
        $braces = substr_count($uri, '{');

        if ($braces !== substr_count($uri, '}') || count($matches[0]) !== $braces) {
            return [sprintf('Invalid URI [%s] for [%s].', $uri, $descriptor)];
        }

        $problems = [];
        $parameters = array_map(fn (ReflectionParameter $parameter): string => $parameter->getName(), $method->getParameters());

        foreach (array_map(self::parameterName(...), $matches[1]) as $name) {
            if (! in_array($name, $parameters, true)) {
                $problems[] = sprintf('Route placeholder [%s] has no matching parameter for [%s].', $name, $descriptor);
            }
        }

        if (str_contains($uri, '//') || preg_match('/\}[^\/]*\{/', $uri)) {
            $problems[] = sprintf('Invalid URI [%s] for [%s].', $uri, $descriptor);
        }

        $optionalSeen = false;
        foreach (array_filter(explode('/', trim($uri, '/')), fn (string $segment): bool => $segment !== '') as $segment) {
            $optional = (bool) preg_match('/\{[^}]+\?\}/', $segment);
            if ($optionalSeen && ! $optional) {
                $problems[] = sprintf('Optional route parameters must be trailing in URI [%s] for [%s].', $uri, $descriptor);

                break;
            }
            $optionalSeen = $optionalSeen || $optional;
        }

        return $problems;
    }

    private static function parameterName(string $placeholder): string
    {
        return rtrim(explode(':', $placeholder, 2)[0], '?');
    }

    /** @param  list<string>  $controller */
    private function assemble(array $controller, ?string $methodUri): string
    {
        $this->bindings = $this->bindings();
        $parts = [];

        foreach ($controller as $segment) {
            foreach (explode('/', trim($segment, '/')) as $part) {
                if ($part !== '') {
                    $parts[] = $this->bind(str_contains($part, '{') ? $part : Str::kebab(Str::studly($part)));
                }
            }
        }

        $action = Str::kebab($this->method->getName());
        $methodParts = $methodUri !== null
            ? explode('/', trim($methodUri, '/'))
            : (ResourceActions::hasUriSegment($action) ? [$action] : []);

        if ($methodUri !== null && $action === 'index') {
            array_pop($parts);
        }

        $methodBindsItself = $methodUri !== null && preg_match(self::PLACEHOLDER, $methodUri);
        if (! $methodBindsItself && $this->bindings !== [] && ! $this->bindings[0]['optional']) {
            $parts[] = self::placeholder(array_shift($this->bindings));
        }

        foreach ($methodParts as $part) {
            $parts[] = $this->bind($part);
        }

        foreach ($this->bindings as $binding) {
            $parts[] = self::placeholder($binding);
        }

        return implode('/', array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    private function bind(string $part): string
    {
        if (! preg_match(self::PLACEHOLDER, $part, $match)) {
            return $part === 'index' ? '' : $part;
        }

        if ($this->bindings === []) {
            $this->errors[] = "Not enough parameters to bind route segment [{$part}] for {$this->descriptor}";

            return $part;
        }

        $binding = array_shift($this->bindings);
        $replacement = ctype_digit(self::parameterName($match[1])) ? self::placeholder($binding) : $match[0];

        return str_replace($match[0], $replacement, $part);
    }

    /** @return list<array{name: string, optional: bool}> */
    private function bindings(): array
    {
        $bindings = [];

        foreach ($this->method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType) {
                $class = $type->getName();
                $stringBacked = enum_exists($class) && (new ReflectionEnum($class))->getBackingType()?->getName() === 'string';

                if ($type->isBuiltin() || is_subclass_of($class, Model::class) || $stringBacked) {
                    $bindings[] = ['name' => $parameter->getName(), 'optional' => $parameter->isOptional() || $type->allowsNull()];
                } elseif (enum_exists($class)) {
                    $this->errors[] = sprintf(
                        'Enum [%s] for parameter $%s in %s is %s and cannot be used for implicit route binding.',
                        $class,
                        $parameter->getName(),
                        $this->descriptor,
                        is_subclass_of($class, BackedEnum::class) ? 'not string-backed' : 'a unit enum',
                    );
                }
            }
        }

        return $bindings;
    }

    /** @param  array{name: string, optional: bool}  $binding */
    private static function placeholder(array $binding): string
    {
        return '{'.$binding['name'].($binding['optional'] ? '?' : '').'}';
    }
}
