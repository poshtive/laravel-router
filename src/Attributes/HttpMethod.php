<?php

declare(strict_types=1);

namespace Poshtive\Router\Attributes;

/**
 * Base for #[Get], #[Post], #[Put], #[Patch], and #[Delete].
 *
 * A method may carry several verb attributes; they register one route that
 * answers every declared verb. A uri starting with "/" ignores the folder
 * segments (the group prefix still applies).
 */
abstract class HttpMethod
{
    public function __construct(
        public ?string $uri = null,
        public ?string $name = null,
    ) {}

    abstract public function verb(): string;
}
