<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

use Countable;
use JsonSerializable;

final class DiscoveredRoutes implements Countable, JsonSerializable
{
    /** @param list<DiscoveredRouteEntry> $entries */
    /** @param list<Diagnostic> $diagnostics */
    public function __construct(
        private readonly array $entries,
        private readonly array $diagnostics,
    ) {}

    /** @return list<DiscoveredRouteEntry> */
    public function all(): array
    {
        return $this->entries;
    }

    public function forGroup(string $name): self
    {
        return new self(
            array_values(array_filter(
                $this->entries,
                fn (DiscoveredRouteEntry $entry): bool => $entry->group === $name,
            )),
            $this->diagnostics,
        );
    }

    public function forStatus(RouteStatus $status): self
    {
        return new self(
            array_values(array_filter(
                $this->entries,
                fn (DiscoveredRouteEntry $entry): bool => $entry->status === $status,
            )),
            $this->diagnostics,
        );
    }

    /** @return list<DiscoveredRouteEntry> */
    public function routes(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (DiscoveredRouteEntry $entry): bool => $entry->status === RouteStatus::Registered,
        ));
    }

    /** @return list<Diagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'entries' => array_map(
                fn (DiscoveredRouteEntry $entry): array => $entry->toArray(),
                $this->entries,
            ),
            'diagnostics' => array_map(
                fn (Diagnostic $diagnostic): array => $diagnostic->toArray(),
                $this->diagnostics,
            ),
            'total' => count($this->entries),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
