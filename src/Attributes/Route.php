<?php

declare(strict_types=1);

namespace Poshtive\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route implements DiscoveryAttribute
{
    /**
     * @param  string|list<string>|null  $method
     * @param  string|list<string>|null  $middleware
     */
    public function __construct(
        public ?string $uri = null,
        public array|string|null $method = null,
        public ?string $name = null,
        public array|string|null $middleware = null,
        public bool $keepOrder = false,
        public bool $absolute = false,
        public bool $scopeBindings = false,
        public bool $withoutScopedBindings = false,
    ) {}
}
