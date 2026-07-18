<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Poshtive\Router\Discovery\BuildFingerprint;
use Poshtive\Router\Discovery\DiscoveredRouteEntry;
use Poshtive\Router\Discovery\RouteStatus;
use Tests\TestCase;

final class BuildFingerprintTest extends TestCase
{
    #[Test]
    public function it_generates_same_fingerprint_for_same_entries(): void
    {
        $entries = $this->makeEntries();
        $fp1 = BuildFingerprint::generate($entries, '2.1.0');
        $fp2 = BuildFingerprint::generate($entries, '2.1.0');

        $this->assertSame($fp1, $fp2);
    }

    #[Test]
    public function it_generates_different_fingerprint_for_different_entries(): void
    {
        $entries1 = $this->makeEntries();
        $entries2 = [$this->makeEntry('different-id')];

        $fp1 = BuildFingerprint::generate($entries1, '2.1.0');
        $fp2 = BuildFingerprint::generate($entries2, '2.1.0');

        $this->assertNotSame($fp1, $fp2);
    }

    #[Test]
    public function it_is_deterministic_regardless_of_order(): void
    {
        $entries1 = [$this->makeEntry('id-b'), $this->makeEntry('id-a')];
        $entries2 = [$this->makeEntry('id-a'), $this->makeEntry('id-b')];

        $fp1 = BuildFingerprint::generate($entries1, '2.1.0');
        $fp2 = BuildFingerprint::generate($entries2, '2.1.0');

        $this->assertSame($fp1, $fp2);
    }

    #[Test]
    public function it_verifies_correctly(): void
    {
        $entries = $this->makeEntries();
        $fp = BuildFingerprint::generate($entries, '2.1.0');

        $this->assertTrue(BuildFingerprint::verify($fp, $entries, '2.1.0'));
    }

    #[Test]
    public function it_rejects_invalid_fingerprint(): void
    {
        $entries = $this->makeEntries();
        BuildFingerprint::generate($entries, '2.1.0');

        $this->assertFalse(BuildFingerprint::verify('wrong-fingerprint', $entries, '2.1.0'));
    }

    #[Test]
    public function it_handles_empty_entries(): void
    {
        $fp = BuildFingerprint::generate([], '2.1.0');
        $this->assertNotEmpty($fp);
        $this->assertSame(64, strlen($fp));
    }

    #[Test]
    public function it_handles_different_package_versions(): void
    {
        $entries = $this->makeEntries();
        $fp1 = BuildFingerprint::generate($entries, '2.0.0');
        $fp2 = BuildFingerprint::generate($entries, '2.1.0');

        $this->assertNotSame($fp1, $fp2);
    }

    /** @return list<DiscoveredRouteEntry> */
    private function makeEntries(): array
    {
        return [
            $this->makeEntry('id-a'),
            $this->makeEntry('id-b'),
        ];
    }

    private function makeEntry(string $id): DiscoveredRouteEntry
    {
        return new DiscoveredRouteEntry(
            id: $id,
            group: 'web',
            status: RouteStatus::Registered,
            methods: ['GET', 'HEAD'],
            uri: '/user',
            name: 'user.index',
            domain: null,
            controller: 'App\\Controller',
            method: 'index',
            sourceFile: 'app/Controller.php',
            sourceLine: 10,
            middleware: [],
            wheres: [],
            scopeBindings: false,
            withoutScopedBindings: false,
            skipReason: null,
            invalidReason: null,
            discardReason: null,
            provenance: [],
        );
    }
}
