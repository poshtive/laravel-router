<?php

namespace Poshtive\Router;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use ReflectionClass;
use ReflectionMethod;

class RouteRegistrar
{
    private string $basePath = '';
    private string $rootNamespace = '';

    public function __construct(private Router $router)
    {
        $this->basePath = \base_path();
    }

    public function useBasePath(string $basePath): self
    {
        if (!empty($basePath) && is_dir($basePath)) {
            $this->basePath = $basePath;
        }
        return $this;
    }

    public function useRootNamespace(string $rootNamespace): self
    {
        if (!empty($rootNamespace)) {
            $this->rootNamespace = $rootNamespace;
        }
        return $this;
    }

    public function registerDirectory(string $directory): void
    {
        $definitions = $this->discoverRoutes($directory);
        $this->reportSkippedRoutes($definitions);
        $this->guardAgainstDuplicates($definitions);
        $this->registerRoutes($definitions);
    }

    protected function discoverRoutes(string $directory): array
    {
        $files = (new Finder())->files()->in($directory)->name('*.php');
        $initialDefinitions = [];
        $extends = \config('router.method_extends', false);

        foreach ($files as $file) {
            $className = $this->fullyQualifiedClassNameFromFile($file);
            if (!class_exists($className)) continue;

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) continue;

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (!$extends && $method->getDeclaringClass()->getName() !== $reflection->getName()) continue;
                if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) continue;
                if (str_starts_with($method->getName(), '__')) continue;

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

    private function registerRoutes(array $definitions): void
    {
        usort($definitions, fn(RouteDefinition $a, RouteDefinition $b) => [
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
            if (empty($routeDef->httpVerb) || !$routeDef->isDiscoverable) {
                continue;
            }

            $router = $this->router->addRoute($routeDef->httpVerb, $uri, $routeDef->action);
            $router->name($routeDef->name);

            if (!empty($routeDef->middleware)) {
                $router->middleware($routeDef->middleware);
            }

            if (!empty($routeDef->wheres)) {
                $router->setWheres($routeDef->wheres);
            }
        }
    }

    private function reportSkippedRoutes(array $definitions): void
    {
        if (!\config('router.report_skipped_routes', false)) {
            return;
        }

        foreach ($definitions as $definition) {
            if ($definition->isDiscoverable || $definition->skipReason === null) {
                continue;
            }

            $this->reportMessage($definition->skipReason, 'info');
        }
    }

    private function guardAgainstDuplicates(array $definitions): void
    {
        $messages = array_merge(
            $this->findDuplicateRouteNames($definitions),
            $this->findDuplicateRouteUris($definitions),
        );

        if ($messages === []) {
            return;
        }

        if (\config('router.strict', false)) {
            throw new RouteDiscoveryException($messages);
        }

        foreach ($messages as $message) {
            $this->reportMessage($message);
        }
    }

    private function findDuplicateRouteNames(array $definitions): array
    {
        $messages = [];
        $routesByName = [];

        foreach ($definitions as $definition) {
            if (!$definition->isDiscoverable || $definition->name === '') {
                continue;
            }

            if (!isset($routesByName[$definition->name])) {
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

    private function findDuplicateRouteUris(array $definitions): array
    {
        $messages = [];
        $routesBySignature = [];

        foreach ($definitions as $definition) {
            if (!$definition->isDiscoverable) {
                continue;
            }

            foreach ($definition->getHttpVerbs() as $verb) {
                $signature = sprintf('%s %s', $verb, $definition->getRegisteredUri());

                if (!isset($routesBySignature[$signature])) {
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

    private function reportMessage(string $message, string $level = 'warning'): void
    {
        $container = \app();

        if (!is_object($container) || !method_exists($container, 'bound') || !$container->bound('log')) {
            return;
        }

        $logger = $container->make('log');

        if (!is_object($logger) || !method_exists($logger, $level)) {
            return;
        }

        $logger->{$level}(sprintf('[laravel-router] %s', $message));
    }

    private function fullyQualifiedClassNameFromFile(SplFileInfo $file): string
    {
        $class = trim(Str::replaceFirst($this->basePath, '', (string)$file->getRealPath()), DIRECTORY_SEPARATOR);
        $class = str_replace(
            [DIRECTORY_SEPARATOR, 'App\\'],
            ['\\', \app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );

        return $this->rootNamespace . $class;
    }
}
