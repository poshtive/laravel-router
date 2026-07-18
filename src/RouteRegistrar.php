<?php

declare(strict_types=1);

namespace Poshtive\Router;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class RouteRegistrar
{
    private string $basePath = '';

    private string $rootNamespace = '';

    /** @var array<string, mixed> */
    private array $group = [];

    private string $discoveryBasePath = '';

    private string $discoveryDirectory = '';

    /** @var list<Diagnostic|string> */
    private array $diagnostics = [];

    /** @var list<RouteDefinition> */
    private array $discardedDefinitions = [];

    private string $currentGroupName = '';

    public function __construct(private Router $router)
    {
        $this->basePath = \base_path();
    }

    public function useBasePath(string $basePath): self
    {
        if (! empty($basePath) && is_dir($basePath)) {
            $this->basePath = $basePath;
        }

        return $this;
    }

    public function useRootNamespace(string $rootNamespace): self
    {
        $this->rootNamespace = $rootNamespace;

        return $this;
    }

    /** @return list<RouteDefinition> */
    public function discoverDirectory(string $directory): array
    {
        $definitions = $this->discoverRoutes($directory);
        $this->applyGroup($definitions);

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

    /** @param array<string, mixed> $group */
    public function forGroup(array $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function useGroupName(string $name): self
    {
        $this->currentGroupName = $name;

        return $this;
    }

    public function groupName(): string
    {
        return $this->currentGroupName;
    }

    /** @return list<Diagnostic|string> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /** @return list<RouteDefinition> */
    public function discardedDefinitions(): array
    {
        return $this->discardedDefinitions;
    }

    /** @return list<RouteDefinition> */
    protected function discoverRoutes(string $directory): array
    {
        $this->discoveryBasePath = dirname($directory);
        $this->discoveryDirectory = $directory;
        if (! is_dir($directory)) {
            return [];
        }
        $finder = (new Finder)->files()->in($directory)->sortByName();
        foreach ((array) ($this->group['patterns'] ?? ['*.php']) as $pattern) {
            $finder->name($pattern);
        }
        foreach ((array) ($this->group['not_patterns'] ?? []) as $pattern) {
            $finder->notName($pattern);
        }
        $files = $finder;
        $initialDefinitions = [];
        $extends = \config('router.method_extends', false);

        foreach ($files as $file) {
            $className = $this->fullyQualifiedClassNameFromFile($file);
            if (! class_exists($className)) {
                $this->diagnostics[] = sprintf('Class [%s] could not be loaded from [%s].', $className, $file->getRelativePathname());

                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) {
                $this->diagnostics[] = sprintf('Skipped abstract controller [%s].', $className);

                continue;
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (! $extends && $method->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }
                if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                    continue;
                }
                if (str_starts_with($method->getName(), '__')) {
                    continue;
                }

                $initialDefinitions[] = new RouteDefinition($file, $reflection, $method, $className, discoveryOrder: count($initialDefinitions));
            }
        }

        return \app(Pipeline::class)
            ->send($initialDefinitions)
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
            if ((! $routeDef->isDiscoverable && ! $routeDef->isFallbackVerb) || ($routeDef->httpVerb === '' && ! $routeDef->isFallbackVerb)) {
                continue;
            }

            $verb = $routeDef->isFallbackVerb ? $routeDef->fallbackHttpVerb : $routeDef->httpVerb;
            $methods = is_array($verb) ? $verb : [$verb];
            $action = $routeDef->action;
            if (is_array($action) && count($action) === 2 && is_string($action[0]) && is_string($action[1])) {
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
            if ($routeDef->scopeBindings) {
                $route->scopeBindings();
            }
            if ($routeDef->withoutScopedBindings) {
                $route->withoutScopedBindings();
            }

            if ($this->currentGroupName !== '') {
                $discoveryId = hash('xxh32', "{$this->currentGroupName}\0{$routeDef->fullyQualifiedClassName}\0{$routeDef->method->getName()}");
                $currentAction = $route->getAction();
                $currentAction['_laravel_router'] = [
                    'id' => $discoveryId,
                    'group' => $this->currentGroupName,
                ];
                $route->setAction($currentAction);
            }

            $this->router->getRoutes()->add($route);
        }
    }

    /** @param list<RouteDefinition> $definitions */
    private function applyGroup(array &$definitions): void
    {
        $prefix = trim((string) ($this->group['prefix'] ?? ''), '/');
        $name = (string) ($this->group['name'] ?? '');
        $middleware = (array) ($this->group['middleware'] ?? []);
        foreach ($definitions as $definition) {
            if ($prefix !== '' && $definition->uri !== '') {
                $definition->uri = $prefix.'/'.$definition->uri;
            } elseif ($prefix !== '') {
                $definition->uri = $prefix;
            }
            $definition->name = $name.$definition->name;
            $definition->middleware = array_values(array_unique(array_merge($middleware, $definition->middleware)));
            $definition->domain = $this->group['domain'] ?? null;
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
        $discarded = [];

        foreach ($definitions as $definition) {
            if (! $definition->isDiscoverable && ! $definition->isFallbackVerb) {
                continue;
            }

            $duplicate = false;
            $dupReason = '';

            if ($definition->name !== '' && isset($names[$definition->name])) {
                $duplicate = true;
                $dupReason = sprintf('Duplicate route name [%s].', $definition->name);
            }

            foreach ($definition->getEffectiveHttpVerbs() as $verb) {
                $sig = $this->buildEffectiveSignature($definition, $verb);
                if (isset($signatures[$sig])) {
                    $duplicate = true;
                    $dupReason = sprintf('Duplicate route signature [%s].', $sig);
                }
            }

            if ($duplicate) {
                $discarded[] = $definition;

                continue;
            }

            if ($definition->name !== '') {
                $names[$definition->name] = true;
            }
            foreach ($definition->getEffectiveHttpVerbs() as $verb) {
                $signatures[$this->buildEffectiveSignature($definition, $verb)] = true;
            }
            $unique[] = $definition;
        }

        $this->discardedDefinitions = array_merge($this->discardedDefinitions, $discarded);

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
            if (! $definition->isDiscoverable && ! $definition->isFallbackVerb) {
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
            if ((! $definition->isDiscoverable && ! $definition->isFallbackVerb) || $definition->name === '') {
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
            if (! $definition->isDiscoverable && ! $definition->isFallbackVerb) {
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

    private function fullyQualifiedClassNameFromFile(SplFileInfo $file): string
    {
        if ($this->rootNamespace !== '') {
            $root = array_key_exists('namespace', $this->group)
                ? $this->discoveryDirectory
                : $this->discoveryBasePath;
            $relative = ltrim(str_replace($root, '', (string) $file->getRealPath()), DIRECTORY_SEPARATOR);
            $relative = Str::replaceLast('.php', '', $relative);

            return trim($this->rootNamespace, '\\').'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        }
        $class = trim(Str::replaceFirst($this->basePath, '', (string) $file->getRealPath()), DIRECTORY_SEPARATOR);
        $class = str_replace(
            [DIRECTORY_SEPARATOR, 'App\\'],
            ['\\', \app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );

        return $this->rootNamespace.$class;
    }
}
