<?php

declare(strict_types=1);

namespace Poshtive\Router;

use Illuminate\Support\Str;
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Except;
use Poshtive\Router\Attributes\HttpMethod;
use Poshtive\Router\Attributes\IgnoreParentMiddleware;
use Poshtive\Router\Attributes\LocalOnly;
use Poshtive\Router\Attributes\Middleware;
use Poshtive\Router\Attributes\Only;
use Poshtive\Router\Attributes\Resource;
use Poshtive\Router\Attributes\ScopeBindings;
use Poshtive\Router\Attributes\Where;
use Poshtive\Router\Attributes\WithoutScopedBindings;
use Poshtive\Router\Discovery\GroupConfig;
use Poshtive\Router\Support\ControllerPath;
use Poshtive\Router\Support\DiscoverableMethods;
use Poshtive\Router\Support\ResourceActions;
use Poshtive\Router\Support\RouteUri;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * The route definitions of one controller class in a discovery group.
 *
 * @internal
 */
final class ControllerRoutes
{
    private const array HTTP_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'];

    /** @var list<ReflectionMethod> */
    private readonly array $actions;

    /** @var list<ReflectionClass<object>> */
    private readonly array $parents;

    /** @var array<class-string, list<object>> */
    private array $classAttributes = [];

    /** @var array<class-string, list<object>> */
    private array $parentAttributes = [];

    /** @param  ReflectionClass<object>  $class */
    public function __construct(
        private readonly GroupConfig $group,
        private readonly ReflectionClass $class,
        private readonly ControllerPath $path,
        private readonly bool $local,
    ) {
        $this->actions = DiscoverableMethods::of($class);
        $this->parents = array_map(
            fn (string $parent): ReflectionClass => new ReflectionClass($parent),
            array_reverse(array_values(class_parents($class->getName()) ?: [])),
        );
    }

    /**
     * #[Only] and #[Except] entries that name no action, which are almost
     * always typos and would otherwise be ignored silently.
     *
     * @return list<string>
     */
    public function unknownFilterEntries(): array
    {
        $actions = array_map(fn (ReflectionMethod $method): string => $method->getName(), $this->actions);
        $warnings = [];

        foreach ([...$this->onClass(Only::class), ...$this->onClass(Except::class)] as $filter) {
            foreach (array_diff($filter->methods, $actions) as $unknown) {
                $warnings[] = sprintf(
                    '#[%s] on [%s] lists [%s], which is not an action of the controller.',
                    class_basename($filter),
                    $this->class->getName(),
                    $unknown,
                );
            }
        }

        return $warnings;
    }

    /** @return list<RouteDefinition> */
    public function definitions(): array
    {
        return array_map($this->definition(...), $this->actions);
    }

    private function definition(ReflectionMethod $method): RouteDefinition
    {
        $descriptor = $this->class->getName().'::'.$method->getName();
        $identity = [$this->group->name, $this->class->getName(), $method->getName(), $this->path->relative];

        $skipReason = $this->skipReason($method);
        if ($skipReason !== null) {
            return new RouteDefinition(...$identity, skipReason: sprintf('Skipped %s because %s.', $descriptor, $skipReason));
        }

        $errors = [];
        $verbAttributes = self::instances($method, HttpMethod::class);
        $uris = self::declared(array_map(fn (HttpMethod $attribute): ?string => $attribute->uri, $verbAttributes));
        $names = self::declared(array_map(fn (HttpMethod $attribute): ?string => $attribute->name, $verbAttributes));

        if ($this->onClass(Only::class) !== [] && $this->onClass(Except::class) !== []) {
            $errors[] = sprintf('[%s] cannot use #[Only] and #[Except] together.', $this->class->getName());
        }

        if (count($uris) > 1 || count($names) > 1) {
            $errors[] = sprintf('HTTP method attributes on [%s] declare conflicting uri or name values.', $descriptor);
        }

        $scopeBindings = $this->scopeBindings($method, $descriptor, $errors);
        $resource = $this->onClass(Resource::class)[0] ?? null;

        if ($errors !== []) {
            return new RouteDefinition(...$identity, errors: $errors);
        }

        [$uri, $errors] = RouteUri::build($this->path, $resource?->uri, $uris[0] ?? null, $method, $descriptor);
        if ($errors !== []) {
            return new RouteDefinition(...$identity, errors: $errors);
        }

        $verbs = $verbAttributes === []
            ? ResourceActions::verbsFor($method->getName())
            : array_values(array_unique(array_map(fn (HttpMethod $attribute): string => strtoupper($attribute->verb()), $verbAttributes)));

        if ($this->group->prefix !== '') {
            $uri = $uri === '' ? $this->group->prefix : $this->group->prefix.'/'.$uri;
        }
        $uri = $uri === '' ? '/' : $uri;

        $errors = [
            ...array_map(
                fn (string $verb): string => sprintf('Invalid HTTP method [%s] for [%s].', $verb, $descriptor),
                array_values(array_diff($verbs, self::HTTP_METHODS)),
            ),
            ...RouteUri::problems($uri, $method, $descriptor),
        ];

        if ($errors !== []) {
            return new RouteDefinition(...$identity, errors: $errors);
        }

        return new RouteDefinition(
            ...$identity,
            uri: $uri,
            verbs: $verbs,
            name: $this->group->namePrefix.($names[0] ?? $this->name($method, $resource)),
            middleware: array_values(array_unique([...$this->group->middleware, ...$this->middleware($method)])),
            wheres: $this->wheres($method),
            domain: $this->group->domain,
            scopeBindings: $scopeBindings,
        );
    }

    private function skipReason(ReflectionMethod $method): ?string
    {
        $only = $this->onClass(Only::class)[0] ?? null;
        $except = $this->onClass(Except::class)[0] ?? null;
        $action = $method->getName();

        return match (true) {
            $this->onClass(DoNotDiscover::class) !== [] => sprintf('[%s] is marked with #[DoNotDiscover]', $this->class->getName()),
            self::instances($method, DoNotDiscover::class) !== [] => 'the method is marked with #[DoNotDiscover]',
            $only !== null && $except !== null => null,
            $only !== null && ! in_array($action, $only->methods, true) => 'the method is not listed in #[Only]',
            $except !== null && in_array($action, $except->methods, true) => 'the method is listed in #[Except]',
            ! $this->local && $this->inherited($method, LocalOnly::class) !== [] => '#[LocalOnly] routes are only registered in the local environment',
            default => null,
        };
    }

    /** @param  list<string>  $errors
     */
    private function scopeBindings(ReflectionMethod $method, string $descriptor, array &$errors): ?bool
    {
        $targets = [
            [self::instances($method, ScopeBindings::class), self::instances($method, WithoutScopedBindings::class)],
            [$this->onClass(ScopeBindings::class), $this->onClass(WithoutScopedBindings::class)],
        ];

        foreach ($targets as [$scope, $withoutScope]) {
            if ($scope !== [] && $withoutScope !== []) {
                $errors[] = sprintf('[%s] cannot use #[ScopeBindings] and #[WithoutScopedBindings] on the same target.', $descriptor);
            } elseif ($scope !== [] || $withoutScope !== []) {
                return $scope !== [];
            }
        }

        return null;
    }

    private function name(ReflectionMethod $method, ?Resource $resource): string
    {
        $segments = $this->path->segments();
        if ($resource?->name !== null) {
            $segments[count($segments) - 1] = Str::kebab($resource->name);
        }

        $controller = Str::replaceStart('index.', '', implode('.', $segments));

        return Str::replaceStart('index.', '', $controller.'.'.Str::kebab($method->getName()));
    }

    /** @return list<string> */
    private function middleware(ReflectionMethod $method): array
    {
        $ignoreOnMethod = self::instances($method, IgnoreParentMiddleware::class) !== [];
        $ignoreOnClass = $ignoreOnMethod || $this->onClass(IgnoreParentMiddleware::class) !== [];

        $attributes = [
            ...($ignoreOnClass ? [] : $this->onParents(Middleware::class)),
            ...($ignoreOnMethod ? [] : $this->onClass(Middleware::class)),
            ...self::instances($method, Middleware::class),
        ];

        return array_values(array_unique(array_merge([], ...array_map(fn (Middleware $attribute): array => $attribute->middleware, $attributes))));
    }

    /** @return array<string, string> */
    private function wheres(ReflectionMethod $method): array
    {
        $wheres = [];
        foreach ($this->inherited($method, Where::class) as $where) {
            $wheres[$where->param] = $where->constraint;
        }

        return $wheres;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return list<T>
     */
    private function inherited(ReflectionMethod $method, string $attribute): array
    {
        return [...$this->onParents($attribute), ...$this->onClass($attribute), ...self::instances($method, $attribute)];
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return list<T>
     */
    private function onParents(string $attribute): array
    {
        /** @var list<T> */
        return $this->parentAttributes[$attribute] ??= array_merge([], ...array_map(
            fn (ReflectionClass $parent): array => self::instances($parent, $attribute),
            $this->parents,
        ));
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return list<T>
     */
    private function onClass(string $attribute): array
    {
        /** @var list<T> */
        return $this->classAttributes[$attribute] ??= self::instances($this->class, $attribute);
    }

    /**
     * @template T of object
     *
     * @param  ReflectionClass<object>|ReflectionMethod  $target
     * @param  class-string<T>  $attribute
     * @return list<T>
     */
    private static function instances(ReflectionClass|ReflectionMethod $target, string $attribute): array
    {
        return array_map(
            fn (ReflectionAttribute $instance): object => $instance->newInstance(),
            $target->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF),
        );
    }

    /**
     * @param  list<?string>  $values
     * @return list<string>
     */
    private static function declared(array $values): array
    {
        return array_values(array_unique(array_filter($values, fn (?string $value): bool => $value !== null)));
    }
}
