<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Tests\TestCase;

class AttributeDiscoveryTest extends TestCase
{
    private RouteDiscoveryManager $manager;

    public function test_verb_attributes_set_the_verbs_uri_and_name(): void
    {
        $routes = $this->discoverRoutes('Attributes/Controllers', 'Tests\\Fixtures\\Attributes\\Controllers');

        $this->assertEquals([
            'verb.viewed' => ['GET|HEAD', 'verb/viewed'],
            'verb.created' => ['POST', 'verb/created'],
            'verb.replaced' => ['PUT', 'verb/replaced'],
            'verb.patched' => ['PATCH', 'verb/patched'],
            'verb.deleted' => ['DELETE', 'verb/deleted'],
            'verb.duplicated' => ['POST', 'verb/duplicated'],
            'custom.multi' => ['PATCH|DELETE', 'verb/custom-uri'],
            'verb.absolute' => ['GET|HEAD', 'teams/{team}/members/{member}'],
            'verb.custom-key' => ['GET|HEAD', 'verb/users/{user}'],
            'verb.status' => ['GET|HEAD', 'verb/{status}/status'],
            'verb.optional' => ['GET|HEAD', 'verb/optional/{id?}'],
            'verb.show-account' => ['GET|HEAD', 'verb/show-account'],
        ], array_map(
            fn (Route $route): array => [implode('|', $route->methods()), $route->uri()],
            array_filter($routes, fn (Route $route): bool => str_ends_with($route->getControllerClass(), 'VerbController')),
        ));
        $this->assertSame('slug', $routes['verb.custom-key']->bindingFieldFor('user'));
    }

    public function test_index_controller_and_index_method_add_no_uri_segment(): void
    {
        $routes = $this->discoverRoutes('Attributes/Controllers', 'Tests\\Fixtures\\Attributes\\Controllers');

        $this->assertSame('/', $routes['index']->uri());
        $this->assertSame('{id}', $routes['show']->uri());
    }

    public function test_nested_index_controllers_keep_index_in_their_route_name(): void
    {
        $routes = $this->discoverRoutes('Attributes/Controllers', 'Tests\\Fixtures\\Attributes\\Controllers');

        $this->assertSame('admin', $routes['admin.index.index']->uri());
    }

    public function test_middleware_merges_group_parent_class_and_method_in_order(): void
    {
        $routes = $this->discoverRoutes('Attributes/Controllers', 'Tests\\Fixtures\\Attributes\\Controllers');

        $this->assertSame(
            ['web', 'base', 'base-2', 'mid', 'auth', 'verified', 'can:update,post', 'throttle'],
            $routes['middleware.update']->gatherMiddleware(),
        );
        $this->assertSame(['web', 'bindings', 'audit'], $routes['ignore-on-class.update']->gatherMiddleware());
        $this->assertSame(['web', 'audit'], $routes['ignore-on-method.update']->gatherMiddleware());
    }

    public function test_where_constraints_merge_parent_class_and_method_with_the_closest_winning(): void
    {
        $routes = $this->discoverRoutes('Attributes/Controllers', 'Tests\\Fixtures\\Attributes\\Controllers');

        $this->assertSame([
            'account' => '[a-z]+',
            'team' => '[A-Z]+',
            'region' => '[A-Z]{2}',
            'member' => '[0-9]+',
        ], $routes['where.show']->wheres);
    }

    public function test_conflicting_or_unbindable_actions_are_rejected(): void
    {
        $routes = $this->discoverRoutes('Rejected/Controllers', 'Tests\\Fixtures\\Rejected\\Controllers');

        $this->assertSame(['team.index'], array_keys($routes));
        $expected = [
            'Tests\\Fixtures\\Rejected\\Controllers\\ClassScopeConflictController::show' => 'cannot use #[ScopeBindings] and #[WithoutScopedBindings] on the same target',
            'Tests\\Fixtures\\Rejected\\Controllers\\ConflictingVerbController::uri' => 'declare conflicting uri or name values',
            'Tests\\Fixtures\\Rejected\\Controllers\\ConflictingVerbController::name' => 'declare conflicting uri or name values',
            'Tests\\Fixtures\\Rejected\\Controllers\\EnumController::intStatus' => 'is not string-backed and cannot be used for implicit route binding',
            'Tests\\Fixtures\\Rejected\\Controllers\\Index\\ProfileController::show' => 'Index folder is not allowed in route discovery: Index/ProfileController.php',
            'Tests\\Fixtures\\Rejected\\Controllers\\EnumController::unitStatus' => 'is a unit enum and cannot be used for implicit route binding',
            'Tests\\Fixtures\\Rejected\\Controllers\\MalformedController::coffee' => 'Invalid HTTP method [BREW]',
            'Tests\\Fixtures\\Rejected\\Controllers\\MalformedController::adjacent' => 'Invalid URI [malformed/items/{item}{detail}',
            'Tests\\Fixtures\\Rejected\\Controllers\\MalformedController::orphan' => 'Route placeholder [orphan] has no matching parameter',
            'Tests\\Fixtures\\Rejected\\Controllers\\ScopeConflictController::show' => 'cannot use #[ScopeBindings] and #[WithoutScopedBindings] on the same target',
            'Tests\\Fixtures\\Rejected\\Controllers\\Team\\MemberController::show' => 'Not enough parameters to bind route segment [{1}]',
        ];

        $errors = $this->errors();
        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($errors));
        foreach ($expected as $action => $fragment) {
            $this->assertStringContainsString($fragment, $errors[$action]);
        }
    }

    public function test_skipped_actions_are_not_rejected_for_conflicting_attributes(): void
    {
        $this->discoverRoutes('Rejected/Controllers', 'Tests\\Fixtures\\Rejected\\Controllers');

        $hidden = array_values(array_filter(
            $this->manager->diagnostics(),
            fn ($diagnostic): bool => $diagnostic->action === 'Tests\\Fixtures\\Rejected\\Controllers\\HiddenConflictController::update',
        ));

        $this->assertSame(['route_skipped'], array_map(fn ($diagnostic): string => $diagnostic->code, $hidden));
    }

    public function test_controller_files_without_the_controller_suffix_use_the_class_name(): void
    {
        $routes = $this->discoverRoutes('Attributes/Controllers', 'Tests\\Fixtures\\Attributes\\Controllers');

        $this->assertSame('helpers/{id}', $routes['helpers.show']->uri());
    }

    /** @return array<string, Route> */
    private function discoverRoutes(string $path, string $namespace): array
    {
        config()->set('router.strict', false);

        $this->manager = new RouteDiscoveryManager(app('router'));
        $this->manager->discover([
            'test' => ['paths' => [$this->fixturePath($path)], 'namespace' => $namespace, 'middleware' => ['web']],
        ]);

        $routes = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->getControllerClass() ?? '', $namespace)) {
                $routes[$route->getName()] = $route;
            }
        }

        return $routes;
    }

    /** @return array<string, string> */
    private function errors(): array
    {
        $errors = [];
        foreach ($this->manager->diagnostics() as $diagnostic) {
            if ($diagnostic->code === 'route_invalid') {
                $errors[(string) $diagnostic->action] = $diagnostic->message;
            }
        }

        return $errors;
    }
}
