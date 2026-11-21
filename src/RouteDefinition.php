<?php

declare(strict_types=1);

namespace Poshtive\Router;

/**
 * What discovery decided for one controller action: a route ready to
 * register, or the reason it was skipped, or why it cannot be registered.
 */
final readonly class RouteDefinition
{
    /**
     * @param  string  $path  Controller file inside its discovery directory, for diagnostics.
     * @param  string  $uri  The URI as registered, including the group prefix.
     * @param  list<string>  $verbs
     * @param  list<string>  $middleware
     * @param  array<string, string>  $wheres
     * @param  bool|null  $scopeBindings  true: scope bindings, false: never scope, null: Laravel default.
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $group,
        public string $controller,
        public string $action,
        public string $path,
        public string $uri = '/',
        public array $verbs = [],
        public string $name = '',
        public array $middleware = [],
        public array $wheres = [],
        public ?string $domain = null,
        public ?bool $scopeBindings = null,
        public ?string $skipReason = null,
        public array $errors = [],
    ) {}

    public function descriptor(): string
    {
        return $this->controller.'::'.$this->action;
    }

    /**
     * The verbs Laravel answers, which adds HEAD to every GET route.
     *
     * @return list<string>
     */
    public function effectiveVerbs(): array
    {
        return in_array('GET', $this->verbs, true) ? array_values(array_unique([...$this->verbs, 'HEAD'])) : $this->verbs;
    }
}
