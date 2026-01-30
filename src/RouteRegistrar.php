<?php

namespace Poshtive\Router;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
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
        $this->basePath = base_path();
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
        $this->registerRoutes($definitions);
    }

    protected function discoverRoutes(string $directory): array
    {
        $files = (new Finder())->files()->in($directory)->name('*.php');
        $initialDefinitions = [];
        $extends = config('router.method_extends', false);

        foreach ($files as $file) {
            $className = $this->fullyQualifiedClassNameFromFile($file);
            if (!class_exists($className)) continue;

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract()) continue;

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (!$extends && $method->getDeclaringClass()->getName() !== $reflection->getName()) continue;
                if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) continue;
                if (str_starts_with($method->getName(), '__')) continue;

                $initialDefinitions[] = new RouteDefinition($file, $reflection, $method, $className);
            }
        }

        return app(Pipeline::class)
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
        usort($definitions, fn(RouteDefinition $a, RouteDefinition $b) => $a->getPriorityScore() <=> $b->getPriorityScore());
        foreach ($definitions as $routeDef) {
            $uri = $routeDef->uri;
            if ($uri === '') {
                $uri = '/';
            }
            if (empty($routeDef->httpVerb) || empty($uri) || !$routeDef->isDiscoverable) {
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

    private function fullyQualifiedClassNameFromFile(SplFileInfo $file): string
    {
        $class = trim(Str::replaceFirst($this->basePath, '', (string)$file->getRealPath()), DIRECTORY_SEPARATOR);
        $class = str_replace(
            [DIRECTORY_SEPARATOR, 'App\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );

        return $this->rootNamespace . $class;
    }
}
