<?php

namespace Tests\Unit;

use Poshtive\Router\Support\ControllerPath;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

class ControllerPathTest extends TestCase
{
    public function test_names_are_used_as_written_by_default(): void
    {
        $this->assertSame(['buku', 'native-device'], $this->path('Buku/NativeDeviceController.php')->segments());
    }

    public function test_pluralization_uses_the_laravel_pluralizer_on_the_last_word(): void
    {
        $this->assertSame(['people', 'native-devices'], $this->path('Person/NativeDeviceController.php', pluralize: true)->segments());
    }

    public function test_index_segments_are_never_pluralized(): void
    {
        $this->assertSame(['index'], $this->path('IndexController.php', pluralize: true)->segments());
    }

    public function test_folders_with_a_sibling_controller_add_a_numbered_placeholder(): void
    {
        $this->assertSame(
            ['user', '{1}', 'post', '{2}', 'comment'],
            $this->path('User/Post/CommentController.php', directory: 'DeepNesting/Controllers')->uriSegments(),
        );
    }

    public function test_only_folders_named_index_are_index_folders(): void
    {
        $this->assertTrue($this->path('Admin/Index/ProfileController.php')->hasIndexFolder());
        $this->assertFalse($this->path('Admin/IndexController.php')->hasIndexFolder());
    }

    private function path(string $relative, bool $pluralize = false, string $directory = 'Missing'): ControllerPath
    {
        $relative = str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $pathname = $this->fixturePath($directory).DIRECTORY_SEPARATOR.$relative;

        return ControllerPath::of(new SplFileInfo($pathname, dirname($relative), $relative), $pluralize);
    }
}
