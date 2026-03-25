<?php

namespace Poshtive\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route implements DiscoveryAttribute
{
    public function __construct(
        public ?string $uri = null,
        public array|string|null $method = null,
        public array|string|null $middleware = null,
        public bool $keepOrder = false,
    ) {}
}
