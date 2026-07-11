<?php

namespace Poshtive\Router\Discovery;

final class RouteGroup
{
    public function __construct(public readonly string $name, public readonly array $options) {}

    public function paths(): array
    {
        return array_values((array) ($this->options['paths'] ?? []));
    }
}
