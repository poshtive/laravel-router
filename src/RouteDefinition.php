<?php

namespace Poshtive\Router;

use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

class RouteDefinition
{
    public string $name = '';

    public string $uri = '';

    public string|array $httpVerb = '';

    public array $action = [];

    public array $middleware = [];

    public array $wheres = [];

    public ?string $skipReason = null;

    public bool $keepOrder = false;

    public bool $isDiscoverable = true;

    private array $attributeCache = [];

    private array $attributeInstanceCache = [];

    public function __construct(
        public SplFileInfo $file,
        public ReflectionClass $class,
        public ReflectionMethod $method,
        public string $fullyQualifiedClassName,
        public array $parentAttributes = [],
        public int $discoveryOrder = 0,
    ) {
        $this->action = [$fullyQualifiedClassName, $method->getName()];
    }

    public function markSkipped(string $reason): void
    {
        $this->isDiscoverable = false;
        $this->skipReason = $reason;
    }

    public function descriptor(): string
    {
        return sprintf('%s::%s', $this->fullyQualifiedClassName, $this->method->getName());
    }

    public function getHttpVerbs(): array
    {
        if ($this->httpVerb === '') {
            return [];
        }

        $verbs = is_array($this->httpVerb) ? $this->httpVerb : [$this->httpVerb];

        return array_values(array_unique(array_map('strtoupper', $verbs)));
    }

    public function getRegisteredUri(): string
    {
        return $this->uri === '' ? '/' : $this->uri;
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

    public function hasClassAttribute(string $name, int $flags = 0): bool
    {
        return $this->classAttributes($name, $flags) !== [];
    }

    public function hasMethodAttribute(string $name, int $flags = 0): bool
    {
        return $this->methodAttributes($name, $flags) !== [];
    }

    public function classAttributeInstances(string $name, int $flags = 0): array
    {
        return $this->attributeInstances('class', $name, $flags);
    }

    public function methodAttributeInstances(string $name, int $flags = 0): array
    {
        return $this->attributeInstances('method', $name, $flags);
    }

    public static function httpVerbPrefixFor(string $methodName): ?string
    {
        $verbs = ['get', 'post', 'put', 'patch', 'delete', 'options'];

        foreach ($verbs as $verb) {
            if (! Str::startsWith($methodName, $verb)) {
                continue;
            }

            $actionName = Str::substr($methodName, strlen($verb));
            if ($actionName === '' || ctype_upper($actionName[0])) {
                return $verb;
            }
        }

        return null;
    }

    private function classAttributes(string $name, int $flags = 0): array
    {
        return $this->attributes('class', $name, $flags);
    }

    private function methodAttributes(string $name, int $flags = 0): array
    {
        return $this->attributes('method', $name, $flags);
    }

    private function attributes(string $target, string $name, int $flags): array
    {
        $key = $this->attributeCacheKey($target, $name, $flags);

        if (! array_key_exists($key, $this->attributeCache)) {
            $reflector = $target === 'class' ? $this->class : $this->method;
            $this->attributeCache[$key] = $reflector->getAttributes($name, $flags);
        }

        return $this->attributeCache[$key];
    }

    private function attributeInstances(string $target, string $name, int $flags): array
    {
        $key = $this->attributeCacheKey($target, $name, $flags);

        if (! array_key_exists($key, $this->attributeInstanceCache)) {
            $this->attributeInstanceCache[$key] = array_map(
                fn (ReflectionAttribute $attribute) => $attribute->newInstance(),
                $this->attributes($target, $name, $flags),
            );
        }

        return $this->attributeInstanceCache[$key];
    }

    private function attributeCacheKey(string $target, string $name, int $flags): string
    {
        return sprintf('%s:%s:%d', $target, $name, $flags);
    }

    private function stripVerbFromMethod(string $methodName): string
    {
        if (\config('router.convention') !== 'prefix') {
            return $methodName;
        }

        $verb = self::httpVerbPrefixFor($methodName);
        if ($verb !== null) {
            $methodName = Str::substr($methodName, strlen($verb));
        }

        if ($methodName === '') {
            throw new RuntimeException('Method name cannot be empty after stripping verb prefix.');
        }

        return $methodName;
    }
}
