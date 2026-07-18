<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Poshtive\Router\Discovery\DiscoveredRouteEntry;
use Poshtive\Router\Discovery\Provenance;
use Poshtive\Router\Discovery\RouteStatus;
use Poshtive\Router\RouteDefinition;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Fixtures\RouteDiscovery\Controllers\UserController;
use Tests\TestCase;

final class DiscoveredRouteEntryTest extends TestCase
{
    #[Test]
    public function it_constructs_from_route_definition(): void
    {
        $controller = $this->fixturePath('RouteDiscovery/Controllers/UserController.php');
        $file = new SplFileInfo($controller, '', $controller);

        $class = new ReflectionClass(UserController::class);
        $method = new ReflectionMethod(UserController::class, 'index');

        $def = new RouteDefinition($file, $class, $method, UserController::class);
        $def->httpVerb = 'GET';
        $def->uri = '/user';
        $def->name = 'user.index';

        $entry = DiscoveredRouteEntry::fromRouteDefinition($def, RouteStatus::Registered, 'web');

        $this->assertNotEmpty($entry->id);
        $this->assertSame('web', $entry->group);
        $this->assertSame(RouteStatus::Registered, $entry->status);
        $this->assertContains('GET', $entry->methods);
        $this->assertContains('HEAD', $entry->methods);
        $this->assertSame('/user', $entry->uri);
        $this->assertSame('user.index', $entry->name);
        $this->assertSame(UserController::class, $entry->controller);
        $this->assertSame('index', $entry->method);
        $this->assertNotNull($entry->sourceLine);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $entry = new DiscoveredRouteEntry(
            id: 'abc123',
            group: 'web',
            status: RouteStatus::Registered,
            methods: ['GET', 'HEAD'],
            uri: '/user',
            name: 'user.index',
            domain: null,
            controller: 'App\\Http\\Controllers\\UserController',
            method: 'index',
            sourceFile: 'app/Http/Controllers/UserController.php',
            sourceLine: 15,
            middleware: ['web'],
            wheres: ['id' => '[0-9]+'],
            scopeBindings: false,
            withoutScopedBindings: false,
            skipReason: null,
            invalidReason: null,
            discardReason: null,
            provenance: [Provenance::Convention->value],
        );

        $data = $entry->toArray();

        $this->assertSame('abc123', $data['id']);
        $this->assertSame('web', $data['group']);
        $this->assertSame('registered', $data['status']);
        $this->assertSame(['GET', 'HEAD'], $data['methods']);
        $this->assertSame('/user', $data['uri']);
        $this->assertSame('user.index', $data['name']);
        $this->assertNull($data['domain']);
        $this->assertSame('App\\Http\\Controllers\\UserController', $data['controller']);
        $this->assertSame('index', $data['method']);
        $this->assertSame('app/Http/Controllers/UserController.php', $data['source_file']);
        $this->assertSame(15, $data['source_line']);
        $this->assertSame(['web'], $data['middleware']);
        $this->assertSame(['id' => '[0-9]+'], $data['wheres']);
        $this->assertFalse($data['scope_bindings']);
        $this->assertFalse($data['without_scoped_bindings']);
        $this->assertNull($data['skip_reason']);
        $this->assertNull($data['invalid_reason']);
        $this->assertNull($data['discard_reason']);
        $this->assertSame([Provenance::Convention->value], $data['provenance']);
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $entry = new DiscoveredRouteEntry(
            id: 'abc123',
            group: 'web',
            status: RouteStatus::Skipped,
            methods: [],
            uri: '/',
            name: '',
            domain: null,
            controller: 'App\\Http\\Controllers\\TestController',
            method: 'helper',
            sourceFile: 'app/Http/Controllers/TestController.php',
            sourceLine: 20,
            middleware: [],
            wheres: [],
            scopeBindings: false,
            withoutScopedBindings: false,
            skipReason: 'Marked with DoNotDiscover',
            invalidReason: null,
            discardReason: null,
            provenance: [],
        );

        $json = json_encode($entry);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame('skipped', $decoded['status']);
        $this->assertSame('Marked with DoNotDiscover', $decoded['skip_reason']);
    }

    #[Test]
    public function it_tracks_all_statuses(): void
    {
        foreach ([RouteStatus::Registered, RouteStatus::Skipped, RouteStatus::Invalid, RouteStatus::Discarded] as $status) {
            $entry = new DiscoveredRouteEntry(
                id: 'id-'.$status->value,
                group: 'web',
                status: $status,
                methods: [],
                uri: '/',
                name: '',
                domain: null,
                controller: 'Test',
                method: 'test',
                sourceFile: 'test.php',
                sourceLine: 1,
                middleware: [],
                wheres: [],
                scopeBindings: false,
                withoutScopedBindings: false,
                skipReason: null,
                invalidReason: null,
                discardReason: null,
                provenance: [],
            );

            $this->assertSame($status, $entry->status);
            $data = $entry->toArray();
            $this->assertSame($status->value, $data['status']);
        }
    }

    #[Test]
    public function source_file_is_relative_to_base_path_from_factory(): void
    {
        $controller = $this->fixturePath('RouteDiscovery/Controllers/UserController.php');
        $file = new SplFileInfo($controller, '', $controller);

        $class = new ReflectionClass(UserController::class);
        $method = new ReflectionMethod(UserController::class, 'index');

        $def = new RouteDefinition($file, $class, $method, UserController::class);
        $def->httpVerb = 'GET';
        $def->uri = '/';

        $entry = DiscoveredRouteEntry::fromRouteDefinition($def, RouteStatus::Registered, 'web');

        $data = $entry->toArray();
        $this->assertStringContainsString('UserController.php', $data['source_file']);
        $this->assertStringNotContainsString(base_path(), $data['source_file']);
    }

    #[Test]
    public function source_file_inside_base_path_is_stripped(): void
    {
        $bp = base_path();
        $tempFile = $bp.'/app/Http/TestController.php';
        @mkdir(dirname($tempFile), 0755, true);
        file_put_contents($tempFile, '<?php class TestController { public function index() {} }');

        try {
            $file = new SplFileInfo($tempFile, '', $tempFile);
            $class = new ReflectionClass(UserController::class);
            $method = new ReflectionMethod(UserController::class, 'index');

            $def = new RouteDefinition($file, $class, $method, UserController::class);
            $def->httpVerb = 'GET';
            $def->uri = '/';

            $entry = DiscoveredRouteEntry::fromRouteDefinition($def, RouteStatus::Registered, 'web');
            $data = $entry->toArray();

            $this->assertSame('app/Http/TestController.php', $data['source_file']);
        } finally {
            @unlink($tempFile);
        }
    }
}
