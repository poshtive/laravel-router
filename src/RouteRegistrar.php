<?php

declare(strict_types=1);

namespace Poshtive\Router;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\GroupConfig;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Poshtive\Router\Support\DiscoverableMethods;
use Poshtive\Router\Support\Path;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class RouteRegistrar
{
    /** @var list<Diagnostic|string> */
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

    /** @param list<RouteDefinition> $definitions */
    public function registerDefinitions(array $definitions): void
    {
        $this->validateDefinitions($definitions);
        $this->reportSkippedRoutes($definitions);
        $definitions = $this->guardAgainstDuplicates($definitions);
        $this->registerRoutes($definitions);
    }

    /** @return list<Diagnostic|string> */
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
                $this->diagnostics[] = sprintf('Class [%s] could not be loaded from [%s].', $className, $file->getRelativePathname());

                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) {
                $this->diagnostics[] = sprintf('Skipped abstract controller [%s].', $className);

                continue;
            }

            foreach (DiscoverableMethods::of($reflection) as $method) {
                $definition = new RouteDefinition($file, $reflection, $method, $className, discoveryOrder: count($definitions));
                $definition->group = $group->name;
                $definition->pluralize = $group->pluralize;
                $definitions[] = $definition;
            }
        }

        return $definitions;
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

        foreach ($definitions as $routeDef) {
            $uri = $routeDef->getRegisteredUri();
            $methods = $routeDef->getHttpVerbs();
            if (! $routeDef->isDiscoverable || $methods === []) {
                continue;
            }

            $action = $routeDef->action;
            if (count($action) === 2) {
                $uses = $action[0].'@'.$action[1];
                $action = ['uses' => $uses, 'controller' => $uses];
            }
            $route = $this->router->newRoute($methods, $uri, $action);

            if ($routeDef->domain !== null) {
                $route->domain($routeDef->domain);
            }

            $route->name($routeDef->name);

            if (! empty($routeDef->middleware)) {
                $route->middleware($routeDef->middleware);
            }

            if (! empty($routeDef->wheres)) {
                $route->setWheres($routeDef->wheres);
            }
            if ($routeDef->scopeBindings === true) {
                $route->scopeBindings();
            } elseif ($routeDef->scopeBindings === false) {
                $route->withoutScopedBindings();
            }

            if ($routeDef->group !== '') {
                $route->setAction([...$route->getAction(), '_laravel_router' => [
                    'id' => hash('xxh32', "{$routeDef->group}\0{$routeDef->fullyQualifiedClassName}\0{$routeDef->method->getName()}"),
                    'group' => $routeDef->group,
                ]]);
            }

            $this->router->getRoutes()->add($route);
        }
    }

    /** @param list<RouteDefinition> $definitions */
    private function reportSkippedRoutes(array $definitions): void
    {
        foreach ($definitions as $definition) {
            if ($definition->isDiscoverable || $definition->skipReason === null) {
                continue;
            }

            $this->diagnostics[] = $definition->skipReason;
            if ($definition->invalidReason !== null) {
                $this->reportMessage($definition->skipReason);
            } elseif (\config('router.report_skipped_routes', false)) {
                $this->reportMessage($definition->skipReason, 'info');
            }
        }
    }

    /** @param list<RouteDefinition> $definitions
     * @return list<RouteDefinition>
     */
    private function guardAgainstDuplicates(array $definitions): array
    {
        $messages = array_merge(
            $this->findDuplicateRouteNames($definitions),
            $this->findDuplicateRouteUris($definitions),
        );

        if ($messages === []) {
            return $definitions;
        }

        if (\config('router.strict', false)) {
            throw new RouteDiscoveryException($messages);
        }

        foreach ($messages as $message) {
            $this->diagnostics[] = $message;
            $this->reportMessage($message);
        }

        return $this->removeDuplicateDefinitions($definitions);
    }

    /** @param list<RouteDefinition> $definitions
     * @return list<RouteDefinition>
     */
    private function removeDuplicateDefinitions(array $definitions): array
    {
        $names = [];
        $signatures = [];
        $unique = [];

        foreach ($definitions as $definition) {
            if (! $definition->isDiscoverable) {
                continue;
            }

            $effectiveSignatures = array_map(
                fn (string $verb): string => $this->buildEffectiveSignature($definition, $verb),
                $definition->getEffectiveHttpVerbs(),
            );

            $duplicateName = $definition->name !== '' && isset($names[$definition->name]);
            $duplicateSignature = array_intersect_key(array_flip($effectiveSignatures), $signatures) !== [];

            if ($duplicateName || $duplicateSignature) {
                continue;
            }

            if ($definition->name !== '') {
                $names[$definition->name] = true;
            }
            foreach ($effectiveSignatures as $signature) {
                $signatures[$signature] = true;
            }
            $unique[] = $definition;
        }

        return $unique;
    }

    /** @param list<RouteDefinition> $definitions */
    private function validateDefinitions(array $definitions): void
    {
        $messages = [];
        $validMethods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'];
        foreach ($definitions as $definition) {
            if ($definition->invalidReason !== null) {
                $messages[] = $definition->invalidReason;

                continue;
            }
            if (! $definition->isDiscoverable) {
                continue;
            }
            if (config('router.strict_naming', false) && $definition->name === '') {
                $messages[] = sprintf('Route name cannot be empty for [%s].', $definition->descriptor());
            }
            foreach ($definition->getHttpVerbs() as $verb) {
                if (! in_array($verb, $validMethods, true)) {
                    $message = sprintf('Invalid HTTP method [%s] for [%s].', $verb, $definition->descriptor());
                    $definition->markInvalid($message);
                    $messages[] = $message;
                }
            }
            $uri = $definition->getRegisteredUri();
            if (preg_match('/[{}]/', $uri)) {
                preg_match_all('/\{([^}]+)\}/', $uri, $matches);
                $openingBraces = substr_count($uri, '{');
                $closingBraces = substr_count($uri, '}');
                $validPlaceholders = $openingBraces === $closingBraces
                    && count($matches[0]) === $openingBraces;

                if (! $validPlaceholders) {
                    $message = sprintf('Invalid URI [%s] for [%s].', $uri, $definition->descriptor());
                    $definition->markInvalid($message);
                    $messages[] = $message;

                    continue;
                }

                $parameters = array_map(fn ($parameter) => $parameter->getName(), $definition->method->getParameters());
                foreach ($matches[1] as $placeholder) {
                    $placeholder = explode(':', $placeholder, 2)[0];
                    $placeholder = rtrim($placeholder, '?');
                    if (! in_array($placeholder, $parameters, true)) {
                        $message = sprintf('Route placeholder [%s] has no matching parameter for [%s].', $placeholder, $definition->descriptor());
                        $definition->markInvalid($message);
                        $messages[] = $message;
                    }
                }
            }
            if (str_contains($uri, '//') || preg_match('/\}[^\/]*\{/', $uri)) {
                $message = sprintf('Invalid URI [%s] for [%s].', $uri, $definition->descriptor());
                $definition->markInvalid($message);
                $messages[] = $message;
            }
            $optionalSeen = false;
            foreach (array_filter(explode('/', trim($uri, '/')), fn (string $segment) => $segment !== '') as $segment) {
                $optional = (bool) preg_match('/\{[^}]+\?\}/', $segment);
                if ($optionalSeen && ! $optional) {
                    $message = sprintf('Optional route parameters must be trailing in URI [%s] for [%s].', $uri, $definition->descriptor());
                    $definition->markInvalid($message);
                    $messages[] = $message;

                    break;
                }
                $optionalSeen = $optionalSeen || $optional;
            }
        }
        if ($messages === []) {
            return;
        }
        if (config('router.strict', false) || config('router.strict_naming', false)) {
            throw new RouteDiscoveryException($messages);
        }
        foreach ($messages as $message) {
            $this->diagnostics[] = $message;
            $this->reportMessage($message);
        }
    }

    /** @param list<RouteDefinition> $definitions
     * @return list<string>
     */
    private function findDuplicateRouteNames(array $definitions): array
    {
        $messages = [];
        $routesByName = [];

        foreach ($definitions as $definition) {
            if (! $definition->isDiscoverable || $definition->name === '') {
                continue;
            }

            if (! isset($routesByName[$definition->name])) {
                $routesByName[$definition->name] = $definition;

                continue;
            }

            $messages[] = sprintf(
                'Discovered duplicate route name [%s] for [%s] and [%s].',
                $definition->name,
                $routesByName[$definition->name]->descriptor(),
                $definition->descriptor(),
            );
        }

        return array_values(array_unique($messages));
    }

    /** @param list<RouteDefinition> $definitions
     * @return list<string>
     */
    private function findDuplicateRouteUris(array $definitions): array
    {
        $messages = [];
        $routesBySignature = [];

        foreach ($definitions as $definition) {
            if (! $definition->isDiscoverable) {
                continue;
            }

            foreach ($definition->getEffectiveHttpVerbs() as $verb) {
                $signature = $this->buildEffectiveSignature($definition, $verb);

                if (! isset($routesBySignature[$signature])) {
                    $routesBySignature[$signature] = $definition;

                    continue;
                }

                $messages[] = sprintf(
                    'Discovered duplicate route signature [%s] for [%s] and [%s].',
                    $signature,
                    $routesBySignature[$signature]->descriptor(),
                    $definition->descriptor(),
                );
            }
        }

        return array_values(array_unique($messages));
    }

    private function buildEffectiveSignature(RouteDefinition $definition, string $effectiveVerb): string
    {
        $domain = $definition->domain ?? '*';

        return sprintf('%s %s %s', $domain, $effectiveVerb, $definition->getRegisteredUri());
    }

    private function reportMessage(string $message, string $level = 'warning'): void
    {
        $container = \app();

        if (! is_object($container) || ! method_exists($container, 'bound') || ! $container->bound('log')) {
            return;
        }

        $logger = $container->make('log');

        if (! is_object($logger) || ! method_exists($logger, $level)) {
            return;
        }

        $logger->{$level}(sprintf('[laravel-router] %s', $message));
    }
}
