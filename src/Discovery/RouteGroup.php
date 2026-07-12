<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

final class RouteGroup
{
    /** @param array<string, mixed> $options */
    public function __construct(public readonly string $name, public readonly array $options) {}

    /** @return list<mixed> */
    public function paths(): array
    {
        return array_values((array) ($this->options['paths'] ?? []));
    }
}
