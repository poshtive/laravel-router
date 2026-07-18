<?php

namespace Tests\Unit;

use Illuminate\Routing\Router as IlluminateRouter;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Poshtive\Router\RouteDefinition;
use Poshtive\Router\RouteRegistrar;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Support\ArrayLogger;
use Tests\TestCase;

class RouteRegistrarTest extends TestCase
{
    public function test_discover_routes_filters_abstract_magic_static_and_constructor_methods(): void
    {
        config()->set('router.method_extends', false);

        $registrar = $this->makeRegistrar();
        $registrar->useBasePath($this->fixturePath('Registrar'));
        $registrar->useRootNamespace('Tests\\Fixtures\\Registrar\\');

        $definitions = $registrar->discoverForTest($this->fixturePath('Registrar/Controllers'));

        $descriptors = array_map(fn (RouteDefinition $definition) => $definition->descriptor(), $definitions);

        $this->assertSame([
            'Tests\\Fixtures\\Registrar\\Controllers\\ConcreteController::show',
        ], $descriptors);
    }

    public function test_discover_routes_includes_inherited_methods_when_enabled(): void
    {
        config()->set('router.method_extends', true);

        $registrar = $this->makeRegistrar();
        $registrar->useBasePath($this->fixturePath('Registrar'));
        $registrar->useRootNamespace('Tests\\Fixtures\\Registrar\\');

        $definitions = $registrar->discoverForTest($this->fixturePath('Registrar/Controllers'));

        $descriptors = array_map(fn (RouteDefinition $definition) => $definition->descriptor(), $definitions);

        $this->assertContains('Tests\\Fixtures\\Registrar\\Controllers\\ConcreteController::show', $descriptors);
        $this->assertContains('Tests\\Fixtures\\Registrar\\Controllers\\ConcreteController::inherited', $descriptors);
    }

    public function test_report_skipped_routes_ignores_discoverable_entries(): void
    {
        $registrar = $this->makeRegistrar();
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.report_skipped_routes', true);

        $discoverable = $this->makeDefinition(RegistrarDefinitionController::class, 'alpha');
        $skipped = $this->makeDefinition(RegistrarDefinitionController::class, 'beta');
        $skipped->markSkipped('Skipped beta');

        $this->invokePrivate($registrar, 'reportSkippedRoutes', [[$discoverable, $skipped]]);

        $this->assertSame(['[laravel-router] Skipped beta'], $logger->infoMessages);
    }

    public function test_guard_against_duplicates_reports_duplicate_names_when_not_strict(): void
    {
        $registrar = $this->makeRegistrar();
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $first = $this->makeNamedDefinition('shared.name', 'alpha', 'GET');
        $second = $this->makeNamedDefinition('shared.name', 'beta', 'GET');
        $skipped = $this->makeNamedDefinition('skipped.name', 'gamma', 'GET');
        $skipped->markSkipped('skip');

        $this->invokePrivate($registrar, 'guardAgainstDuplicates', [[$first, $second, $skipped]]);

        $this->assertCount(1, $logger->warningMessages);
        $this->assertStringContainsString('duplicate route name [shared.name]', $logger->warningMessages[0]);
    }

    public function test_guard_against_duplicates_throws_when_strict_mode_is_enabled(): void
    {
        $registrar = $this->makeRegistrar();
        config()->set('router.strict', true);

        $first = $this->makeNamedDefinition('shared.name', 'alpha', 'GET');
        $second = $this->makeNamedDefinition('shared.name', 'beta', 'GET');

        $this->expectException(RouteDiscoveryException::class);
        $this->expectExceptionMessage('duplicate route name [shared.name]');

        $this->invokePrivate($registrar, 'guardAgainstDuplicates', [[$first, $second]]);
    }

    public function test_report_message_returns_when_logger_is_missing_or_does_not_support_level(): void
    {
        $registrar = $this->makeRegistrar();

        app()->offsetUnset('log');
        $this->invokePrivate($registrar, 'reportMessage', ['ignored']);

        app()->instance('log', new \stdClass);
        $this->invokePrivate($registrar, 'reportMessage', ['ignored']);

        $this->assertTrue(true);
    }

    public function test_discovery_ignores_a_missing_directory(): void
    {
        $registrar = $this->makeRegistrar();

        $this->assertSame([], $registrar->discoverForTest('/path/that/does/not/exist'));
    }

    public function test_discovery_can_derive_the_namespace_from_the_application_path(): void
    {
        $registrar = $this->makeRegistrar();
        $registrar->useBasePath($this->packageBasePath());

        $this->assertNotEmpty($registrar->discoverForTest($this->fixturePath('Registrar/Controllers')));
    }

    public function test_manager_derives_the_application_namespace_for_application_paths(): void
    {
        $path = app_path('Controllers');
        @mkdir($path, 0777, true);

        try {
            (new RouteDiscoveryManager($this->app->make(IlluminateRouter::class)))->discover([
                'app' => ['paths' => [$path]],
            ]);
            $this->assertTrue(true);
        } finally {
            @rmdir($path);
            @rmdir(dirname($path));
        }
    }

    public function test_default_application_controller_path_is_discovered_without_a_duplicated_namespace_segment(): void
    {
        $path = app_path('Http/Controllers');
        @mkdir($path, 0777, true);
        $file = $path.'/HealthController.php';
        file_put_contents($file, '<?php namespace App\\Http\\Controllers; class HealthController { public function index(): void {} }');
        require_once $file;

        try {
            $manager = new RouteDiscoveryManager($this->app->make(IlluminateRouter::class));
            $manager->discover([
                'web' => ['paths' => [$path]],
            ]);

            $route = collect($this->app->make('router')->getRoutes()->getRoutes())
                ->first(fn ($route) => $route->getActionName() === 'App\\Http\\Controllers\\HealthController@index');

            $this->assertNotNull($route);
            $this->assertSame('health', $route->uri());
        } finally {
            app()->offsetUnset('routes.cached');
            @unlink($file);
            @rmdir($path);
            @rmdir(dirname($path));
            @rmdir(dirname(dirname($path)));
        }
    }

    public function test_manager_ignores_invalid_group_paths(): void
    {
        (new RouteDiscoveryManager($this->app->make(IlluminateRouter::class)))->discover([
            'missing' => ['paths' => ['/path/that/does/not/exist']],
        ]);

        $this->assertTrue(true);
    }

    public function test_invalid_methods_throw_in_strict_validation_mode(): void
    {
        $registrar = $this->makeRegistrar();
        $definition = $this->makeNamedDefinition('invalid', 'alpha', 'BREW');
        config()->set('router.strict', true);

        $this->expectException(RouteDiscoveryException::class);
        $this->expectExceptionMessage('Invalid HTTP method [BREW]');

        $this->invokePrivate($registrar, 'validateDefinitions', [[$definition]]);
    }

    public function test_empty_names_throw_in_strict_naming_mode(): void
    {
        $registrar = $this->makeRegistrar();
        $definition = $this->makeNamedDefinition('', 'alpha', 'GET');
        config()->set('router.strict_naming', true);

        $this->expectException(RouteDiscoveryException::class);
        $this->expectExceptionMessage('Route name cannot be empty');

        $this->invokePrivate($registrar, 'validateDefinitions', [[$definition]]);
    }

    public function test_guard_against_duplicates_reports_duplicate_route_signatures(): void
    {
        $registrar = $this->makeRegistrar();
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $first = $this->makeNamedDefinition('first', 'alpha', 'GET');
        $second = $this->makeNamedDefinition('second', 'beta', 'GET');
        $second->uri = 'alpha';

        $this->invokePrivate($registrar, 'guardAgainstDuplicates', [[$first, $second]]);

        $this->assertStringContainsString('duplicate route signature [* GET alpha]', $logger->warningMessages[0]);
    }

    public function test_same_uri_with_different_http_methods_is_not_a_duplicate(): void
    {
        app()->instance('log', new ArrayLogger);
        config()->set('router.strict', true);

        $get = $this->makeNamedDefinition('get.alpha', 'alpha', 'GET');
        $post = $this->makeNamedDefinition('post.alpha', 'beta', 'POST');
        $post->uri = 'alpha';

        $this->makeRegistrar()->registerDefinitions([$get, $post]);

        $routes = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'alpha')
            ->values();

        $this->assertCount(2, $routes);
        $this->assertSame(['GET', 'HEAD'], $routes[0]->methods());
        $this->assertSame(['POST'], $routes[1]->methods());
    }

    public function test_register_definitions_registers_routes_in_priority_order_with_metadata(): void
    {
        app()->instance('log', new ArrayLogger);
        config()->set('router.strict', false);
        config()->set('router.report_skipped_routes', false);

        $registrar = $this->makeRegistrar();
        $first = $this->makeNamedDefinition('zeta', 'alpha', ['GET']);
        $first->uri = 'posts/{post}';
        $first->middleware = ['auth'];
        $first->wheres = ['post' => '[0-9]+'];

        $second = $this->makeNamedDefinition('alpha', 'beta', ['GET']);
        $second->uri = 'posts';

        $skipped = $this->makeNamedDefinition('skip', 'gamma', ['GET']);
        $skipped->uri = 'skipped';
        $skipped->markSkipped('skip');

        $emptyVerb = $this->makeNamedDefinition('no-verb', 'delta', '');
        $emptyVerb->uri = 'no-verb';

        $registrar->registerDefinitions([$first, $second, $skipped, $emptyVerb]);

        $routes = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array($route->getName(), ['alpha', 'zeta'], true))
            ->values();

        $this->assertCount(2, $routes);
        $this->assertSame('alpha', $routes[0]->getName());
        $this->assertSame('posts', $routes[0]->uri());
        $this->assertSame('zeta', $routes[1]->getName());
        $this->assertSame('posts/{post}', $routes[1]->uri());
        $this->assertSame(['auth'], $routes[1]->gatherMiddleware());
        $this->assertSame(['post' => '[0-9]+'], $routes[1]->wheres);
    }

    public function test_strict_validation_is_atomic_before_registration(): void
    {
        $registrar = $this->makeRegistrar();
        config()->set('router.strict', true);

        $valid = $this->makeNamedDefinition('valid', 'alpha', 'GET');
        $invalid = $this->makeNamedDefinition('invalid', 'beta', 'BREW');

        try {
            $registrar->registerDefinitions([$valid, $invalid]);
            $this->fail('Expected invalid HTTP method to throw.');
        } catch (RouteDiscoveryException) {
            $this->assertNull($this->app->make('router')->getRoutes()->getByName('valid'));
        }
    }

    public function test_get_and_explicit_head_on_same_domain_and_uri_is_a_duplicate(): void
    {
        $registrar = $this->makeRegistrar();
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $get = $this->makeNamedDefinition('get.health', 'alpha', 'GET');
        $get->uri = 'health';
        $head = $this->makeNamedDefinition('head.health', 'beta', 'HEAD');
        $head->uri = 'health';

        $this->invokePrivate($registrar, 'guardAgainstDuplicates', [[$get, $head]]);

        $this->assertCount(1, $logger->warningMessages);
        $this->assertStringContainsString('duplicate route signature [* HEAD health]', $logger->warningMessages[0]);
    }

    public function test_get_on_different_domains_is_not_a_duplicate(): void
    {
        app()->instance('log', new ArrayLogger);
        config()->set('router.strict', true);

        $first = $this->makeNamedDefinition('first.health', 'alpha', 'GET');
        $first->uri = 'health';
        $first->domain = 'admin.example.com';
        $second = $this->makeNamedDefinition('second.health', 'beta', 'GET');
        $second->uri = 'health';
        $second->domain = 'api.example.com';

        $this->makeRegistrar()->registerDefinitions([$first, $second]);

        $routes = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'health')
            ->values();

        $this->assertCount(2, $routes);
    }

    public function test_get_and_head_with_different_uris_is_not_a_duplicate(): void
    {
        app()->instance('log', new ArrayLogger);
        config()->set('router.strict', true);

        $get = $this->makeNamedDefinition('get.alpha', 'alpha', 'GET');
        $get->uri = 'alpha';
        $head = $this->makeNamedDefinition('head.beta', 'beta', 'HEAD');
        $head->uri = 'beta';

        $this->makeRegistrar()->registerDefinitions([$get, $head]);

        $this->assertNotNull($this->app->make('router')->getRoutes()->getByName('get.alpha'));
        $this->assertNotNull($this->app->make('router')->getRoutes()->getByName('head.beta'));
    }

    public function test_same_route_name_on_different_domains_is_a_duplicate(): void
    {
        $registrar = $this->makeRegistrar();
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $first = $this->makeNamedDefinition('shared.name', 'alpha', 'GET');
        $first->domain = 'admin.example.com';
        $second = $this->makeNamedDefinition('shared.name', 'beta', 'GET');
        $second->domain = 'api.example.com';

        $this->invokePrivate($registrar, 'guardAgainstDuplicates', [[$first, $second]]);

        $this->assertCount(1, $logger->warningMessages);
        $this->assertStringContainsString('duplicate route name [shared.name]', $logger->warningMessages[0]);
    }

    public function test_multi_verb_definition_expands_get_to_effective_verbs(): void
    {
        $registrar = $this->makeRegistrar();
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $multi = $this->makeNamedDefinition('multi', 'alpha', ['GET', 'POST']);
        $multi->uri = 'health';
        $head = $this->makeNamedDefinition('head-only', 'beta', 'HEAD');
        $head->uri = 'health';

        $this->invokePrivate($registrar, 'guardAgainstDuplicates', [[$multi, $head]]);

        $this->assertCount(1, $logger->warningMessages);
        $this->assertStringContainsString('HEAD health', $logger->warningMessages[0]);
    }

    public function test_non_strict_dedup_keeps_deterministic_first_definition(): void
    {
        app()->instance('log', new ArrayLogger);
        config()->set('router.strict', false);

        $first = $this->makeNamedDefinition('first', 'alpha', 'GET');
        $first->uri = 'health';
        $second = $this->makeNamedDefinition('second', 'beta', 'GET');
        $second->uri = 'health';

        $this->makeRegistrar()->registerDefinitions([$first, $second]);

        $routes = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'health')
            ->values();

        $this->assertCount(1, $routes);
        $this->assertSame('first', $routes[0]->getName());
    }

    public function test_get_effective_verbs_returns_get_and_head_for_get_only(): void
    {
        $definition = $this->makeNamedDefinition('test', 'alpha', 'GET');
        $this->assertSame(['GET', 'HEAD'], $definition->getEffectiveHttpVerbs());
    }

    public function test_get_effective_verbs_returns_single_verb_for_post(): void
    {
        $definition = $this->makeNamedDefinition('test', 'alpha', 'POST');
        $this->assertSame(['POST'], $definition->getEffectiveHttpVerbs());
    }

    public function test_get_effective_verbs_preserves_existing_head(): void
    {
        $definition = $this->makeNamedDefinition('test', 'alpha', ['GET', 'HEAD']);
        $this->assertSame(['GET', 'HEAD'], $definition->getEffectiveHttpVerbs());
    }

    public function test_non_strict_validation_reports_invalid_placeholders_and_uris_without_registering_them(): void
    {
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $missingParameter = $this->makeNamedDefinition('missing', 'alpha', 'GET');
        $missingParameter->uri = 'items/{item}';
        $malformedUri = $this->makeNamedDefinition('malformed', 'beta', 'GET');
        $malformedUri->uri = 'items/{item}{detail}';

        $this->makeRegistrar()->registerDefinitions([$missingParameter, $malformedUri]);

        $this->assertNull($this->app->make('router')->getRoutes()->getByName('missing'));
        $this->assertNull($this->app->make('router')->getRoutes()->getByName('malformed'));
        $messages = implode("\n", $logger->warningMessages);
        $this->assertStringContainsString('item', $messages);
        $this->assertStringContainsString('Invalid URI', $messages);
    }

    private function makeRegistrar(): TestRouteRegistrar
    {
        return new TestRouteRegistrar($this->app->make(IlluminateRouter::class));
    }

    private function makeDefinition(string $className, string $methodName): RouteDefinition
    {
        return new RouteDefinition(
            file: new SplFileInfo(__FILE__, '', class_basename(str_replace('\\', '/', $className)).'.php'),
            class: new \ReflectionClass($className),
            method: new \ReflectionMethod($className, $methodName),
            fullyQualifiedClassName: $className,
        );
    }

    private function makeNamedDefinition(string $name, string $methodName, string|array $verb): RouteDefinition
    {
        $definition = $this->makeDefinition(RegistrarDefinitionController::class, $methodName);
        $definition->name = $name;
        $definition->httpVerb = $verb;
        $definition->uri = $methodName;

        return $definition;
    }

    private function invokePrivate(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invokeArgs($object, $arguments);
    }

    public function test_group_name_tracks_last_set_group(): void
    {
        $registrar = new RouteRegistrar(app('router'));

        $registrar->useGroupName('alpha');
        $this->assertSame('alpha', $registrar->groupName());

        $registrar->useGroupName('beta');
        $this->assertSame('beta', $registrar->groupName());
    }
}

class TestRouteRegistrar extends RouteRegistrar
{
    public function discoverForTest(string $directory): array
    {
        return $this->discoverRoutes($directory);
    }
}

class RegistrarDefinitionController
{
    public function alpha(int $post): void {}

    public function beta(): void {}

    public function gamma(): void {}

    public function delta(): void {}
}
