<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

final readonly class DiscoveryManifest
{
    public const int SCHEMA_VERSION = 1;

    /** @param list<array<string, mixed>> $entries */
    /** @param list<array<string, string>> $diagnostics */
    public function __construct(
        public int $schemaVersion,
        public string $buildFingerprint,
        public array $entries,
        public array $diagnostics,
        public int $totalRoutes,
        public bool $hasZeroRoutes,
        public string $packageVersion,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'build_fingerprint' => $this->buildFingerprint,
            'entries' => $this->entries,
            'diagnostics' => $this->diagnostics,
            'total_routes' => $this->totalRoutes,
            'has_zero_routes' => $this->hasZeroRoutes,
            'package_version' => $this->packageVersion,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            schemaVersion: (int) ($data['schema_version'] ?? 0),
            buildFingerprint: (string) ($data['build_fingerprint'] ?? ''),
            entries: (array) ($data['entries'] ?? []),
            diagnostics: (array) ($data['diagnostics'] ?? []),
            totalRoutes: (int) ($data['total_routes'] ?? 0),
            hasZeroRoutes: (bool) ($data['has_zero_routes'] ?? false),
            packageVersion: (string) ($data['package_version'] ?? ''),
        );
    }

    public function serialize(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public static function deserialize(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \RuntimeException('Manifest JSON must decode to an array.');
        }

        return self::fromArray($data);
    }
}
