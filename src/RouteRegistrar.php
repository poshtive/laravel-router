<?php

declare(strict_types=1);

namespace Poshtive\Router;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Poshtive\Router\Attributes\Except;
use Poshtive\Router\Attributes\Only;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\GroupConfig;
use Poshtive\Router\Discovery\Severity;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Poshtive\Router\Support\DiscoverableMethods;
use Poshtive\Router\Support\Path;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

class RouteRegistrar
{
    private const array HTTP_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'];

    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    public function __construct(private Router $router) {}

    /**
     * Discover the controllers in one directory of a group. The group must
     * carry a resolved namespace for the controllers directly inside it.
     *
     * @return list<RouteDefinition>
     */
    public function discover(GroupConfig $group, string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $definitions = \app(Pipeline::class)
            ->send($this->definitionsIn($group, $directory))
            ->through([
                Pipes\ApplyInheritance::class,
                Pipes\FilterRoutes::class,
                Pipes\ApplyRouteAttributes::class,
                Pipes\BuildUri::class,
                Pipes\BuildHttpVerb::class,
                Pipes\BuildRouteName::class,
                Pipes\ApplyMiddleware::class,
                Pipes\ApplyWhereConstraints::class,
            ])
            ->thenReturn();

        foreach ($definitions as $definition) {
            $this->applyGroup($group, $definition);
        }

        return $definitions;
    }

    /**
     * Validate and deduplicate every definition, then register the routes.
     *
     * Validation is global and atomic: in strict mode any error throws before a
     * single route is registered. Otherwise invalid and duplicate routes are
     * skipped and reported as diagnostics.
     *
     * @param  list<RouteDefinition>  $definitions
     */
    public function registerDefinitions(array $definitions, bool $strict = false): void
    {
        foreach ($definitions as $definition) {
            $this->validate($definition);
        }

        $definitions = $this->withoutDuplicates($definitions);

        $errors = array_values(array_filter(
            $this->diagnostics,
            fn (Diagnostic $diagnostic): bool => $diagnostic->severity === Severity::Error,
        ));

        if ($strict && $errors !== []) {
            throw new RouteDiscoveryException($errors);
        }

        $this->registerRoutes($definitions);
    }

    /** @return list<Diagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /** @return list<RouteDefinition> */
    private function definitionsIn(GroupConfig $group, string $directory): array
    {
        $finder = (new Finder)->files()->in($directory)->name($group->patterns)->notName($group->notPatterns)->sortByName();
        $definitions = [];

        foreach ($finder as $file) {
            $className = $group->namespace.'\\'.Path::toNamespace($file->getRelativePathname());
            if (! class_exists($className)) {
                $this->report('controller_unloadable', Severity::Warning, sprintf('Class [%s] could not be loaded from [%s].', $className, $file->getRelativePathname()), $group->name, $file->getRelativePathname());

                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) {
                $this->report('controller_abstract', Severity::Info, sprintf('Skipped abstract controller [%s].', $className), $group->name, $file->getRelativePathname());

                continue;
            }

            $methods = DiscoverableMethods::of($reflection);
            $this->checkActionFilters($group, $reflection, $methods, $file->getRelativePathname());

            foreach ($methods as $method) {
                $definition = new RouteDefinition($file, $reflection, $method, $className, discoveryOrder: count($definitions));
                $definition->group = $group->name;
                $definition->pluralize = $group->pluralize;
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * Warn about #[Only] and #[Except] entries that name no action, which are
     * almost always typos and would otherwise be ignored silently.
     *
     * @param  ReflectionClass<object>  $class
     * @param  list<ReflectionMethod>  $methods
     */
    private function checkActionFilters(GroupConfig $group, ReflectionClass $class, array $methods, string $path): void
    {
        $actions = array_map(fn (ReflectionMethod $method): string => $method->getName(), $methods);

        foreach ([Only::class, Except::class] as $filter) {
            foreach ($class->getAttributes($filter) as $attribute) {
                foreach (array_diff($attribute->newInstance()->methods, $actions) as $unknown) {
                    $this->report('action_filter_unknown', Severity::Warning, sprintf(
                        '#[%s] on [%s] lists [%s], which is not an action of the controller.',
                        class_basename($filter),
                        $class->getName(),
                        $unknown,
                    ), $group->name, $path);
                }
            }
        }
    }

    private function applyGroup(GroupConfig $group, RouteDefinition $definition): void
    {
        if ($group->prefix !== '') {
            $definition->uri = $definition->uri === '' ? $group->prefix : $group->prefix.'/'.$definition->uri;
        }

        $definition->name = $group->namePrefix.$definition->name;
        $definition->middleware = array_values(array_unique([...$group->middleware, ...$definition->middleware]));
        $definition->domain = $group->domain;
    }

    private function validate(RouteDefinition $definition): void
    {
        if ($definition->invalidReason !== null) {
            $this->reportRoute($definition, 'route_invalid', Severity::Error, $definition->invalidReason);

            return;
        }

        if (! $definition->isDiscoverable) {
            if ($definition->skipReason !== null) {
                $this->reportRoute($definition, 'route_skipped', Severity::Info, $definition->skipReason);
            }

            return;
        }

        foreach ($this->uriProblems($definition) as $problem) {
            $definition->markInvalid($problem);
            $this->reportRoute($definition, 'route_invalid', Severity::Error, $problem);
        }
    }

    /** @return list<string> */
    private function uriProblems(RouteDefinition $definition): array
    {
        $problems = [];
        $descriptor = $definition->descriptor();

        foreach ($definition->getHttpVerbs() as $verb) {
            if (! in_array($verb, self::HTTP_METHODS, true)) {
                $problems[] = sprintf('Invalid HTTP method [%s] for [%s].', $verb, $descriptor);
            }
        }

        $uri = $definition->getRegisteredUri();
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        $braces = substr_count($uri, '{');

        if ($braces !== substr_count($uri, '}') || count($matches[0]) !== $braces) {
            return [...$problems, sprintf('Invalid URI [%s] for [%s].', $uri, $descriptor)];
        }

        $parameters = array_map(fn ($parameter) => $parameter->getName(), $definition->method->getParameters());
        foreach ($matches[1] as $placeholder) {
            $placeholder = rtrim(explode(':', $placeholder, 2)[0], '?');
            if (! in_array($placeholder, $parameters, true)) {
                $problems[] = sprintf('Route placeholder [%s] has no matching parameter for [%s].', $placeholder, $descriptor);
            }
        }

        if (str_contains($uri, '//') || preg_match('/\}[^\/]*\{/', $uri)) {
            $problems[] = sprintf('Invalid URI [%s] for [%s].', $uri, $descriptor);
        }

        $optionalSeen = false;
        foreach (array_filter(explode('/', trim($uri, '/')), fn (string $segment) => $segment !== '') as $segment) {
            $optional = (bool) preg_match('/\{[^}]+\?\}/', $segment);
            if ($optionalSeen && ! $optional) {
                $problems[] = sprintf('Optional route parameters must be trailing in URI [%s] for [%s].', $uri, $descriptor);

                break;
            }
            $optionalSeen = $optionalSeen || $optional;
        }

        return $problems;
    }

    /**
     * Keep the first definition for every route name and every effective
     * domain/verb/URI signature (GET implies HEAD); report and drop the rest.
     *
     * @param  list<RouteDefinition>  $definitions
     * @return list<RouteDefinition>
     */
    private function withoutDuplicates(array $definitions): array
    {
        /** @var array<string, RouteDefinition> $names */
        $names = [];
        /** @var array<string, RouteDefinition> $signatures */
        $signatures = [];
        $unique = [];

        foreach ($definitions as $definition) {
            if (! $definition->isDiscoverable) {
                continue;
            }

            $duplicate = false;

            if ($definition->name !== '' && isset($names[$definition->name])) {
                $duplicate = true;
                $this->reportRoute($definition, 'route_duplicate_name', Severity::Error, sprintf(
                    'Discovered duplicate route name [%s] for [%s] and [%s].',
                    $definition->name,
                    $names[$definition->name]->descriptor(),
                    $definition->descriptor(),
                ));
            }

            $effectiveSignatures = array_map(
                fn (string $verb): string => sprintf('%s %s %s', $definition->domain ?? '*', $verb, $definition->getRegisteredUri()),
                $definition->getEffectiveHttpVerbs(),
            );

            foreach ($effectiveSignatures as $signature) {
                if (isset($signatures[$signature])) {
                    $duplicate = true;
                    $this->reportRoute($definition, 'route_duplicate_signature', Severity::Error, sprintf(
                        'Discovered duplicate route signature [%s] for [%s] and [%s].',
                        $signature,
                        $signatures[$signature]->descriptor(),
                        $definition->descriptor(),
                    ));
                }
            }

            if ($duplicate) {
                continue;
            }

            if ($definition->name !== '') {
                $names[$definition->name] = $definition;
            }
            foreach ($effectiveSignatures as $signature) {
                $signatures[$signature] = $definition;
            }
            $unique[] = $definition;
        }

        return $unique;
    }

    /** @param list<RouteDefinition> $definitions */
    private function registerRoutes(array $definitions): void
    {
        usort($definitions, fn (RouteDefinition $a, RouteDefinition $b) => [
            $a->getPriorityScore(),
            $a->getRegisteredUri(),
            $a->name,
            $a->fullyQualifiedClassName,
            $a->method->getName(),
            $a->discoveryOrder,
        ] <=> [
            $b->getPriorityScore(),
            $b->getRegisteredUri(),
            $b->name,
            $b->fullyQualifiedClassName,
            $b->method->getName(),
            $b->discoveryOrder,
        ]);

        foreach ($definitions as $definition) {
            $methods = $definition->getHttpVerbs();
            if (! $definition->isDiscoverable || $methods === []) {
                continue;
            }

            $uses = $definition->fullyQualifiedClassName.'@'.$definition->method->getName();
            $route = $this->router->newRoute($methods, $definition->getRegisteredUri(), ['uses' => $uses, 'controller' => $uses]);

            if ($definition->domain !== null) {
                $route->domain($definition->domain);
            }

            $route->name($definition->name);

            if ($definition->middleware !== []) {
                $route->middleware($definition->middleware);
            }

            if ($definition->wheres !== []) {
                $route->setWheres($definition->wheres);
            }

            if ($definition->scopeBindings === true) {
                $route->scopeBindings();
            } elseif ($definition->scopeBindings === false) {
                $route->withoutScopedBindings();
            }

            if ($definition->group !== '') {
                $route->setAction([...$route->getAction(), '_laravel_router' => [
                    'id' => hash('xxh32', "{$definition->group}\0{$definition->fullyQualifiedClassName}\0{$definition->method->getName()}"),
                    'group' => $definition->group,
                ]]);
            }

            $this->router->getRoutes()->add($route);
        }
    }

    private function reportRoute(RouteDefinition $definition, string $code, Severity $severity, string $message): void
    {
        $this->report($code, $severity, $message, $definition->group, $definition->file->getRelativePathname(), $definition->descriptor());
    }

    private function report(string $code, Severity $severity, string $message, string $group, string $path, ?string $action = null): void
    {
        $this->diagnostics[] = new Diagnostic($code, $severity, $message, $group, Path::normalize($path), $action);
    }
}
