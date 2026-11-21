<?php

namespace Tests\Unit;

use Poshtive\Router\Support\ResourceSegment;
use Tests\TestCase;

class ResourceSegmentTest extends TestCase
{
    public function test_names_are_used_as_written_by_default(): void
    {
        $this->assertSame('Buku', ResourceSegment::uri('Buku', false));
        $this->assertSame('native-device', ResourceSegment::name('NativeDevice', false));
    }

    public function test_pluralization_uses_the_laravel_pluralizer_on_the_last_word(): void
    {
        $this->assertSame('People', ResourceSegment::uri('Person', true));
        $this->assertSame('NativeDevices', ResourceSegment::uri('NativeDevice', true));
        $this->assertSame('native-devices', ResourceSegment::name('native-device', true));
    }

    public function test_index_segments_are_never_pluralized(): void
    {
        $this->assertSame('Index', ResourceSegment::uri('Index', true));
        $this->assertSame('index', ResourceSegment::name('index', true));
    }
}
