<?php

namespace Tests\Unit;

use Illuminate\Routing\Router as IlluminateRouter;
use Poshtive\Router\Discovery\GroupConfig;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Discovery\Severity;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Poshtive\Router\RouteDefinition;
use Poshtive\Router\RouteRegistrar;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

class RouteRegistrarTest extends TestCase
{
    public function test_discover_routes_filters_abstract_magic_static_and_constructor_methods(): void
    {
        $definitions = $this->makeRegistrar()->discover(
            new GroupConfig('registrar', namespace: 'Tests\\Fixtures\\Registrar\\Controllers'),
            $this->fixturePath('Registrar/Controllers'),
        );

        $descriptors = array_map(fn (RouteDefinition $definition) => $definition->descriptor(), $definitions);

        $this->assertSame([
            'Tests\\Fixtures\\Registrar\\Controllers\\ConcreteController::show',
        ], $descriptors);
    }

    public function test_skipped_definitions_are_reported_as_info(): void
    {
        $registrar = $this->makeRegistrar();
        $discoverable = $this->makeNamedDefinition('alpha', 'alpha', 'GET');
        $skipped = $this->makeDefinition(RegistrarDefinitionController::class, 'beta');
        $skipped->markSkipped('Skipped beta');

        $registrar->registerDefinitions([$discoverable, $skipped]);

        $this->assertCount(1, $registrar->diagnostics());
        $this->assertSame('route_skipped', $registrar->diagnostics()[0]->code);
        $this->assertSame(Severity::Info, $registrar->diagnostics()[0]->severity);
        $this->assertSame('Skipped beta', $registrar->diagnostics()[0]->message);
        $this->assertSame(RegistrarDefinitionController::class.'::beta', $registrar->diagnostics()[0]->action);
    }

    public function test_duplicate_names_are_reported_and_the_later_route_is_dropped(): void
    {
        $registrar = $this->makeRegistrar();
        $first = $this->makeNamedDefinition('shared.name', 'alpha', 'GET');
        $second = $this->makeNamedDefinition('shared.name', 'beta', 'GET');
        $skipped = $this->makeNamedDefinition('skipped.name', 'gamma', 'GET');
        $skipped->markSkipped('skip');

        $registrar->registerDefinitions([$first, $second, $skipped]);

        $messages = $this->messages($registrar, 'route_duplicate_name');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('duplicate route name [shared.name]', $messages[0]);
        $this->assertSame('alpha', $this->app->make('router')->getRoutes()->getByName('shared.name')->uri());
    }

    public function test_duplicates_throw_in_strict_mode_with_structured_diagnostics(): void
    {
        $first = $this->makeNamedDefinition('shared.name', 'alpha', 'GET');
        $second = $this->makeNamedDefinition('shared.name', 'beta', 'GET');

        try {
            $this->makeRegistrar()->registerDefinitions([$first, $second], strict: true);
            $this->fail('Expected duplicate route names to throw.');
        } catch (RouteDiscoveryException $exception) {
            $this->assertStringContainsString('duplicate route name [shared.name]', $exception->getMessage());
            $this->assertSame(['route_duplicate_name'], array_map(fn ($diagnostic) => $diagnostic->code, $exception->diagnostics()));
        }
    }

    public function test_discovery_ignores_a_missing_directory(): void
    {
        $this->assertSame([], $this->makeRegistrar()->discover(new GroupConfig('missing'), '/path/that/does/not/exist'));
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

    public function test_application_path_with_unnormalized_segments_derives_the_correct_namespace(): void
    {
        $path = app_path('Http/Controllers');
        @mkdir($path, 0777, true);
        $file = $path.'/PortablePathController.php';
        file_put_contents($file, '<?php namespace App\\Http\\Controllers; class PortablePathController { public function index(): void {} }');
        require_once $file;

        try {
            $manager = new RouteDiscoveryManager($this->app->make(IlluminateRouter::class));
            $manager->discover([
                'web' => ['paths' => [app_path('Http/../Http//Controllers/')]],
            ]);

            $route = collect($this->app->make('router')->getRoutes()->getRoutes())
                ->first(fn ($route) => $route->getActionName() === 'App\\Http\\Controllers\\PortablePathController@index');

            $this->assertNotNull($route);
            $this->assertSame('portable-path', $route->uri());
        } finally {
            app()->offsetUnset('routes.cached');
            @unlink($file);
            @rmdir($path);
            @rmdir(dirname($path));
            @rmdir(dirname(dirname($path)));
        }
    }

    public function test_paths_outside_the_application_require_a_namespace(): void
    {
        $manager = new RouteDiscoveryManager($this->app->make(IlluminateRouter::class));
        $manager->discover(['modules' => ['paths' => [$this->fixturePath('Registrar/Controllers')]]]);

        $diagnostic = $manager->diagnostics()[0];
        $this->assertSame('discovery_namespace_missing', $diagnostic->code);
        $this->assertSame('modules', $diagnostic->group);
        $this->assertStringEndsWith('tests/Fixtures/Registrar/Controllers', $diagnostic->path);
    }

    public function test_manager_ignores_invalid_group_paths(): void
    {
        (new RouteDiscoveryManager($this->app->make(IlluminateRouter::class)))->discover([
            'missing' => ['paths' => ['/path/that/does/not/exist']],
        ]);

        $this->assertTrue(true);
    }

    public function test_invalid_methods_throw_in_strict_mode(): void
    {
        $definition = $this->makeNamedDefinition('invalid', 'alpha', 'BREW');

        $this->expectException(RouteDiscoveryException::class);
        $this->expectExceptionMessage('Invalid HTTP method [BREW]');

        $this->makeRegistrar()->registerDefinitions([$definition], strict: true);
    }

    public function test_duplicate_signatures_are_reported(): void
    {
        $registrar = $this->makeRegistrar();
        $first = $this->makeNamedDefinition('first', 'alpha', 'GET');
        $second = $this->makeNamedDefinition('second', 'beta', 'GET');
        $second->uri = 'alpha';

        $registrar->registerDefinitions([$first, $second]);

        $this->assertStringContainsString('duplicate route signature [* GET alpha]', $this->messages($registrar, 'route_duplicate_signature')[0]);
    }

    public function test_same_uri_with_different_http_methods_is_not_a_duplicate(): void
    {
        $get = $this->makeNamedDefinition('get.alpha', 'alpha', 'GET');
        $post = $this->makeNamedDefinition('post.alpha', 'beta', 'POST');
        $post->uri = 'alpha';

        $this->makeRegistrar()->registerDefinitions([$get, $post], strict: true);

        $routes = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'alpha')
            ->values();

        $this->assertCount(2, $routes);
        $this->assertSame(['GET', 'HEAD'], $routes[0]->methods());
        $this->assertSame(['POST'], $routes[1]->methods());
    }

    public function test_register_definitions_registers_routes_in_priority_order_with_metadata(): void
    {

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
        $valid = $this->makeNamedDefinition('valid', 'alpha', 'GET');
        $invalid = $this->makeNamedDefinition('invalid', 'beta', 'BREW');

        try {
            $this->makeRegistrar()->registerDefinitions([$valid, $invalid], strict: true);
            $this->fail('Expected invalid HTTP method to throw.');
        } catch (RouteDiscoveryException) {
            $this->assertNull($this->app->make('router')->getRoutes()->getByName('valid'));
        }
    }

    public function test_get_and_explicit_head_on_same_domain_and_uri_is_a_duplicate(): void
    {
        $registrar = $this->makeRegistrar();
        $get = $this->makeNamedDefinition('get.health', 'alpha', 'GET');
        $get->uri = 'health';
        $head = $this->makeNamedDefinition('head.health', 'beta', 'HEAD');
        $head->uri = 'health';

        $registrar->registerDefinitions([$get, $head]);

        $messages = $this->messages($registrar, 'route_duplicate_signature');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('duplicate route signature [* HEAD health]', $messages[0]);
    }

    public function test_get_on_different_domains_is_not_a_duplicate(): void
    {
        $first = $this->makeNamedDefinition('first.health', 'alpha', 'GET');
        $first->uri = 'health';
        $first->domain = 'admin.example.com';
        $second = $this->makeNamedDefinition('second.health', 'beta', 'GET');
        $second->uri = 'health';
        $second->domain = 'api.example.com';

        $this->makeRegistrar()->registerDefinitions([$first, $second], strict: true);

        $routes = collect($this->app->make('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'health')
            ->values();

        $this->assertCount(2, $routes);
    }

    public function test_get_and_head_with_different_uris_is_not_a_duplicate(): void
    {
        $get = $this->makeNamedDefinition('get.alpha', 'alpha', 'GET');
        $get->uri = 'alpha';
        $head = $this->makeNamedDefinition('head.beta', 'beta', 'HEAD');
        $head->uri = 'beta';

        $this->makeRegistrar()->registerDefinitions([$get, $head], strict: true);

        $this->assertNotNull($this->app->make('router')->getRoutes()->getByName('get.alpha'));
        $this->assertNotNull($this->app->make('router')->getRoutes()->getByName('head.beta'));
    }

    public function test_same_route_name_on_different_domains_is_a_duplicate(): void
    {
        $registrar = $this->makeRegistrar();
        $first = $this->makeNamedDefinition('shared.name', 'alpha', 'GET');
        $first->domain = 'admin.example.com';
        $second = $this->makeNamedDefinition('shared.name', 'beta', 'GET');
        $second->domain = 'api.example.com';

        $registrar->registerDefinitions([$first, $second]);

        $this->assertCount(1, $this->messages($registrar, 'route_duplicate_name'));
        $this->assertSame([], $this->messages($registrar, 'route_duplicate_signature'));
    }

    public function test_multi_verb_definition_expands_get_to_effective_verbs(): void
    {
        $registrar = $this->makeRegistrar();
        $multi = $this->makeNamedDefinition('multi', 'alpha', ['GET', 'POST']);
        $multi->uri = 'health';
        $head = $this->makeNamedDefinition('head-only', 'beta', 'HEAD');
        $head->uri = 'health';

        $registrar->registerDefinitions([$multi, $head]);

        $messages = $this->messages($registrar, 'route_duplicate_signature');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('HEAD health', $messages[0]);
    }

    public function test_non_strict_dedup_keeps_deterministic_first_definition(): void
    {

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
        $registrar = $this->makeRegistrar();
        $missingParameter = $this->makeNamedDefinition('missing', 'alpha', 'GET');
        $missingParameter->uri = 'items/{item}';
        $malformedUri = $this->makeNamedDefinition('malformed', 'beta', 'GET');
        $malformedUri->uri = 'items/{item}{detail}';

        $registrar->registerDefinitions([$missingParameter, $malformedUri]);

        $this->assertNull($this->app->make('router')->getRoutes()->getByName('missing'));
        $this->assertNull($this->app->make('router')->getRoutes()->getByName('malformed'));
        $messages = implode("\n", $this->messages($registrar, 'route_invalid'));
        $this->assertStringContainsString('Route placeholder [item]', $messages);
        $this->assertStringContainsString('Invalid URI', $messages);
    }

    private function makeRegistrar(): RouteRegistrar
    {
        return new RouteRegistrar($this->app->make(IlluminateRouter::class));
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

    /** @return list<string> */
    private function messages(RouteRegistrar $registrar, string $code): array
    {
        return array_values(array_map(
            fn ($diagnostic) => $diagnostic->message,
            array_filter($registrar->diagnostics(), fn ($diagnostic) => $diagnostic->code === $code),
        ));
    }
}

class RegistrarDefinitionController
{
    public function alpha(int $post): void {}

    public function beta(): void {}

    public function gamma(): void {}

    public function delta(): void {}
}
