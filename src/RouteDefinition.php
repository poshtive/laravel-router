<?php

namespace Poshtive\Router;

use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

class RouteDefinition
{
    public string $name = '';
    public string $uri = '';
    public string|array $httpVerb = '';
    public array $action = [];
    public array $middleware = [];
    public array $wheres = [];

    public bool $keepOrder = false;
    public bool $isDiscoverable = true;

    public function __construct(
        public SplFileInfo $file,
        public ReflectionClass $class,
        public ReflectionMethod $method,
        public string $fullyQualifiedClassName,
        public array $parentAttributes = [],
    ) {
        $this->action = [$fullyQualifiedClassName, $method->getName()];
    }

    public function getPriorityScore(): int
    {
        $uri = str_replace('}', '', $this->uri);
        return substr_count($uri, '{') * 1000 - strlen($this->uri);
    }

    public function getMethodName(): string
    {
        return Str::kebab($this->stripVerbFromMethod($this->method->getName()));
    }

    private function stripVerbFromMethod(string $methodName): string
    {
        if (config('router.convention') !== 'prefix') {
            return $methodName;
        }

        $verbs = ['get', 'post', 'put', 'patch', 'delete', 'options'];
        foreach ($verbs as $verb) {
            if (Str::startsWith($methodName, $verb)) {
                $methodName = Str::substr($methodName, strlen($verb));
                break;
            }
        }

        if ($methodName === '') {
            throw new RuntimeException("Method name cannot be empty after stripping verb prefix.");
        }

        return $methodName;
    }
}
