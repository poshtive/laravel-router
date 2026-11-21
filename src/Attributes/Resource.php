<?php

declare(strict_types=1);

namespace Poshtive\Router\Attributes;

use Attribute;

/**
 * Override the URI segment and route name segment derived from the class name.
 * A uri starting with "/" replaces every folder-derived segment.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Resource implements DiscoveryAttribute
{
    public function __construct(
        public ?string $uri = null,
        public ?string $name = null,
    ) {}
}
