<?php

namespace Tests\Unit;

use Illuminate\Routing\Router as IlluminateRouter;
use Poshtive\Router\Discovery\GroupConfig;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Poshtive\Router\RouteDefinition;
use Poshtive\Router\RouteRegistrar;
use Tests\TestCase;

class RouteRegistrarTest extends TestCase
{
    public function test_discover_routes_filters_abstract_magic_static_and_constructor_methods(): void
    {
        $definitions = $this->makeRegistrar()->discover(
            new GroupConfig('registrar'),
            $this->fixturePath('Registrar/Controllers'),
            'Tests\\Fixtures\\Registrar\\Controllers',
        );

        $this->assertSame([
            'Tests\\Fixtures\\Registrar\\Controllers\\ConcreteController::show',
        ], array_map(fn (RouteDefinition $definition) => $definition->descriptor(), $definitions));
    }

    public function test_duplicate_names_are_reported_and_the_later_route_is_dropped(): void
    {
        $registrar = $this->makeRegistrar();

        $registrar->registerDefinitions([
            $this->definition('shared.name', 'alpha'),
            $this->definition('shared.name', 'beta'),
        ]);

        $messages = $this->messages($registrar, 'route_duplicate_name');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('duplicate route name [shared.name]', $messages[0]);
        $this->assertSame('alpha', $this->app->make('router')->getRoutes()->getByName('shared.name')->uri());
    }

    public function test_duplicates_throw_in_strict_mode_before_any_route_is_registered(): void
    {
        try {
            $this->makeRegistrar()->registerDefinitions([
                $this->definition('valid', 'gamma'),
                $this->definition('shared.name', 'alpha'),
                $this->definition('shared.name', 'beta'),
            ], strict: true);
            $this->fail('Expected duplicate route names to throw.');
        } catch (RouteDiscoveryException $exception) {
            $this->assertStringContainsString('duplicate route name [shared.name]', $exception->getMessage());
            $this->assertSame(['route_duplicate_name'], array_map(fn ($diagnostic) => $diagnostic->code, $exception->diagnostics()));
            $this->assertNull($this->app->make('router')->getRoutes()->getByName('valid'));
        }
    }

    public function test_duplicate_signatures_keep_the_first_definition(): void
    {
        $registrar = $this->makeRegistrar();

        $registrar->registerDefinitions([
            $this->definition('first', 'alpha', uri: 'health'),
            $this->definition('second', 'beta', uri: 'health'),
        ]);

        $this->assertStringContainsString('duplicate route signature [* GET health]', $this->messages($registrar, 'route_duplicate_signature')[0]);
        $this->assertSame(['first'], $this->routeNames('health'));
    }

    public function test_same_uri_with_different_http_methods_is_not_a_duplicate(): void
    {
        $this->makeRegistrar()->registerDefinitions([
            $this->definition('get.alpha', 'alpha', uri: 'alpha'),
            $this->definition('post.alpha', 'beta', ['POST'], uri: 'alpha'),
        ], strict: true);

        $this->assertSame(['get.alpha', 'post.alpha'], $this->routeNames('alpha'));
    }

    public function test_get_implies_head_when_detecting_duplicate_signatures(): void
    {
        $registrar = $this->makeRegistrar();

        $registrar->registerDefinitions([
            $this->definition('multi', 'alpha', ['GET', 'POST'], uri: 'health'),
            $this->definition('head.health', 'beta', ['HEAD'], uri: 'health'),
            $this->definition('head.other', 'gamma', ['HEAD'], uri: 'other'),
        ]);

        $messages = $this->messages($registrar, 'route_duplicate_signature');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('duplicate route signature [* HEAD health]', $messages[0]);
        $this->assertSame(['head.other'], $this->routeNames('other'));
    }

    public function test_domains_separate_signatures_but_not_names(): void
    {
        $registrar = $this->makeRegistrar();

        $registrar->registerDefinitions([
            $this->definition('first.health', 'alpha', uri: 'health', domain: 'admin.example.com'),
            $this->definition('second.health', 'beta', uri: 'health', domain: 'api.example.com'),
            $this->definition('first.health', 'gamma', uri: 'other', domain: 'api.example.com'),
        ]);

        $this->assertSame(['first.health', 'second.health'], $this->routeNames('health'));
        $this->assertCount(1, $this->messages($registrar, 'route_duplicate_name'));
        $this->assertSame([], $this->messages($registrar, 'route_duplicate_signature'));
    }

    public function test_routes_are_registered_static_first_with_their_metadata(): void
    {
        $this->makeRegistrar()->registerDefinitions([
            $this->definition('zeta', 'alpha', uri: 'posts/{post}', middleware: ['auth'], wheres: ['post' => '[0-9]+']),
            $this->definition('alpha', 'beta', uri: 'posts'),
        ]);

        $routes = array_values(array_filter(
            $this->app->make('router')->getRoutes()->getRoutes(),
            fn ($route) => in_array($route->getName(), ['alpha', 'zeta'], true),
        ));

        $this->assertSame(['posts', 'posts/{post}'], array_map(fn ($route) => $route->uri(), $routes));
        $this->assertSame(['auth'], $routes[1]->gatherMiddleware());
        $this->assertSame(['post' => '[0-9]+'], $routes[1]->wheres);
    }

    private function makeRegistrar(): RouteRegistrar
    {
        return new RouteRegistrar($this->app->make(IlluminateRouter::class), local: false);
    }

    /**
     * @param  list<string>  $verbs
     * @param  list<string>  $middleware
     * @param  array<string, string>  $wheres
     */
    private function definition(string $name, string $action, array $verbs = ['GET'], ?string $uri = null, ?string $domain = null, array $middleware = [], array $wheres = []): RouteDefinition
    {
        return new RouteDefinition(
            group: 'test',
            controller: RegistrarDefinitionController::class,
            action: $action,
            path: 'RegistrarDefinitionController.php',
            uri: $uri ?? $action,
            verbs: $verbs,
            name: $name,
            middleware: $middleware,
            wheres: $wheres,
            domain: $domain,
        );
    }

    /** @return list<string> */
    private function routeNames(string $uri): array
    {
        return array_values(array_map(
            fn ($route) => $route->getName(),
            array_filter($this->app->make('router')->getRoutes()->getRoutes(), fn ($route) => $route->uri() === $uri),
        ));
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
    public function alpha(): void {}

    public function beta(): void {}

    public function gamma(): void {}
}
