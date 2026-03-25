<?php

namespace Poshtive\Router;

class Router
{
    public function __construct(private string $rootNamespace = '', private string $basePath = '') {}

    public function useRootNamespace(string $rootNamespace): self
    {
        $this->rootNamespace = $rootNamespace;

        return $this;
    }

    public function useBasePath(string $basePath): self
    {
        $this->basePath = $basePath;

        return $this;
    }

    public function discover(string $directory): void
    {
        $router = \app()->router;

        \app(RouteRegistrar::class, [$router])
            ->useRootNamespace($this->rootNamespace)
            ->useBasePath($this->basePath)
            ->registerDirectory($directory);
    }

    public static function create(string $rootNamespace = '', string $basePath = ''): self
    {
        return new self($rootNamespace, $basePath);
    }
}
