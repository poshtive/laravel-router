<?php

declare(strict_types=1);

namespace Poshtive\Router\Attributes;

use Attribute;

/**
 * Register only the listed controller actions.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Only
{
    /** @var list<string> */
    public readonly array $methods;

    public function __construct(string ...$methods)
    {
        $this->methods = array_values($methods);
    }
}
