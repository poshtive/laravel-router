<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Poshtive\Router\Discovery\DiscoveryManifest;
use Tests\TestCase;

final class DiscoveryManifestTest extends TestCase
{
    #[Test]
    public function it_serializes_and_deserializes(): void
    {
        $manifest = new DiscoveryManifest(
            schemaVersion: DiscoveryManifest::SCHEMA_VERSION,
            buildFingerprint: 'abc123def456',
            entries: [
                ['id' => 'id1', 'status' => 'registered', 'uri' => '/user'],
            ],
            diagnostics: [
                ['code' => 'test', 'severity' => 'info', 'group' => 'web', 'path' => '', 'message' => 'ok'],
            ],
            totalRoutes: 1,
            hasZeroRoutes: false,
            packageVersion: '2.1.0',
        );

        $json = $manifest->serialize();
        $restored = DiscoveryManifest::deserialize($json);

        $this->assertSame(DiscoveryManifest::SCHEMA_VERSION, $restored->schemaVersion);
        $this->assertSame('abc123def456', $restored->buildFingerprint);
        $this->assertCount(1, $restored->entries);
        $this->assertSame('id1', $restored->entries[0]['id']);
        $this->assertCount(1, $restored->diagnostics);
        $this->assertSame(1, $restored->totalRoutes);
        $this->assertFalse($restored->hasZeroRoutes);
        $this->assertSame('2.1.0', $restored->packageVersion);
    }

    #[Test]
    public function it_converts_to_and_from_array(): void
    {
        $data = [
            'schema_version' => 1,
            'build_fingerprint' => 'fingerprint',
            'entries' => [],
            'diagnostics' => [],
            'total_routes' => 0,
            'has_zero_routes' => true,
            'package_version' => '2.1.0',
        ];

        $manifest = DiscoveryManifest::fromArray($data);
        $this->assertSame(1, $manifest->schemaVersion);
        $this->assertSame('fingerprint', $manifest->buildFingerprint);
        $this->assertTrue($manifest->hasZeroRoutes);

        $roundtripped = $manifest->toArray();
        $this->assertSame($data, $roundtripped);
    }

    #[Test]
    public function it_handles_zero_routes(): void
    {
        $manifest = new DiscoveryManifest(
            schemaVersion: DiscoveryManifest::SCHEMA_VERSION,
            buildFingerprint: 'zero-fingerprint',
            entries: [],
            diagnostics: [],
            totalRoutes: 0,
            hasZeroRoutes: true,
            packageVersion: '2.1.0',
        );

        $this->assertTrue($manifest->hasZeroRoutes);
        $this->assertSame(0, $manifest->totalRoutes);
        $this->assertSame([], $manifest->entries);
    }

    #[Test]
    public function it_handles_missing_fields_in_from_array(): void
    {
        $manifest = DiscoveryManifest::fromArray([]);

        $this->assertSame(0, $manifest->schemaVersion);
        $this->assertSame('', $manifest->buildFingerprint);
        $this->assertSame([], $manifest->entries);
        $this->assertSame([], $manifest->diagnostics);
        $this->assertSame(0, $manifest->totalRoutes);
        $this->assertFalse($manifest->hasZeroRoutes);
        $this->assertSame('', $manifest->packageVersion);
    }

    #[Test]
    public function it_throws_on_non_array_json_deserialize(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must decode to an array');

        DiscoveryManifest::deserialize('"just a string"');
    }
}
