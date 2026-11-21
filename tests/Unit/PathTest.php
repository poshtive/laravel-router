<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poshtive\Router\Support\Path;

class PathTest extends TestCase
{
    public function test_normalize_converts_separators_and_collapses_dot_segments(): void
    {
        $this->assertSame('C:/proj/app/Http/Controllers', Path::normalize('C:\\proj\\app\\Http/Controllers'));
        $this->assertSame('C:/proj/app/Http/Controllers', Path::normalize('C:\\proj\\app\\Http\\..\\Http\\\\Controllers\\'));
        $this->assertSame('/var/www/app', Path::normalize('/var/www/./app/'));
        $this->assertSame('/', Path::normalize('/'));
        $this->assertSame('C:/', Path::normalize('C:\\'));
        $this->assertSame('//server/share/app', Path::normalize('\\\\server\\share\\app'));
        $this->assertSame('/app', Path::normalize('/../app'));
        $this->assertSame('../app', Path::normalize('../app'));
    }

    public function test_relative_handles_mixed_windows_separators(): void
    {
        $this->assertSame(
            'Http/Controllers',
            Path::relative('C:\\proj\\app', 'C:\\proj\\app\\Http/Controllers', caseInsensitive: true),
        );
        $this->assertSame(
            'Api/UserController.php',
            Path::relative('C:\\proj\\app\\Http/Controllers', 'C:\\proj\\app\\Http\\Controllers\\Api\\UserController.php', caseInsensitive: true),
        );
    }

    public function test_relative_is_case_insensitive_only_when_requested(): void
    {
        $this->assertSame('Http', Path::relative('c:\\Proj\\App', 'C:\\proj\\app\\Http', caseInsensitive: true));
        $this->assertNull(Path::relative('/Proj/App', '/proj/app/Http', caseInsensitive: false));
    }

    public function test_relative_requires_a_segment_boundary(): void
    {
        $this->assertNull(Path::relative('/proj/app', '/proj/application/Http', caseInsensitive: false));
        $this->assertSame('', Path::relative('/proj/app', '/proj/app/', caseInsensitive: false));
        $this->assertSame('app/Http', Path::relative('/', '/app/Http', caseInsensitive: false));
    }

    public function test_to_namespace_converts_relative_paths(): void
    {
        $this->assertSame('Http\\Controllers', Path::toNamespace('Http/Controllers'));
        $this->assertSame('Api\\UserController', Path::toNamespace('Api\\UserController.php'));
        $this->assertSame('', Path::toNamespace(''));
    }
}
