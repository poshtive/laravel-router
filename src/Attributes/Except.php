<?php

declare(strict_types=1);

namespace Poshtive\Router\Attributes;

use Attribute;

/**
 * Register every controller action except the listed ones.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Except implements DiscoveryAttribute
{
    /** @var list<string> */
    public readonly array $methods;

    public function __construct(string ...$methods)
    {
        $this->methods = array_values($methods);
    }
}
