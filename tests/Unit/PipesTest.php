<?php

namespace Tests\Unit;

use Illuminate\Routing\Controller;
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\IgnoreParentMiddleware;
use Poshtive\Router\Attributes\LocalOnly;
use Poshtive\Router\Attributes\Route as RouteAttribute;
use Poshtive\Router\Attributes\Where;
use Poshtive\Router\Pipes\ApplyInheritance;
use Poshtive\Router\Pipes\ApplyMiddleware;
use Poshtive\Router\Pipes\ApplyRouteAttributes;
use Poshtive\Router\Pipes\ApplyWhereConstraints;
use Poshtive\Router\Pipes\BuildHttpVerb;
use Poshtive\Router\Pipes\BuildRouteName;
use Poshtive\Router\Pipes\BuildUri;
use Poshtive\Router\Pipes\FilterRoutes;
use Poshtive\Router\RouteDefinition;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

class PipesTest extends TestCase
{
    public function test_apply_inheritance_collects_discovery_attributes_in_root_first_order(): void
    {
        $definition = $this->makeDefinition(InheritanceChildController::class, 'index');

        $result = (new ApplyInheritance)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertCount(3, $result[0]->parentAttributes);
        $this->assertInstanceOf(RouteAttribute::class, $result[0]->parentAttributes[0]);
        $this->assertInstanceOf(RouteAttribute::class, $result[0]->parentAttributes[1]);
        $this->assertInstanceOf(Where::class, $result[0]->parentAttributes[2]);
        $this->assertSame(['base'], (array) $result[0]->parentAttributes[0]->middleware);
        $this->assertSame(['mid'], (array) $result[0]->parentAttributes[1]->middleware);
        $this->assertSame('account', $result[0]->parentAttributes[2]->param);
    }

    public function test_filter_routes_marks_do_not_discover_classes_as_skipped(): void
    {
        $definition = $this->makeDefinition(DoNotDiscoverController::class, 'index');

        $result = (new FilterRoutes)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertFalse($result[0]->isDiscoverable);
        $this->assertStringContainsString('#[DoNotDiscover]', $result[0]->skipReason);
    }

    public function test_filter_routes_marks_local_only_routes_as_skipped_outside_local_environment(): void
    {
        config()->set('app.env', 'testing');
        $definition = $this->makeDefinition(LocalOnlyController::class, 'show');

        $result = (new FilterRoutes)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertFalse($result[0]->isDiscoverable);
        $this->assertStringContainsString('#[LocalOnly]', $result[0]->skipReason);
    }

    public function test_apply_route_attributes_sets_uri_method_and_keep_order(): void
    {
        $definition = $this->makeDefinition(RouteAttributedController::class, 'store');

        $result = (new ApplyRouteAttributes)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('custom-uri', $result[0]->uri);
        $this->assertSame(['PATCH', 'DELETE'], $result[0]->httpVerb);
        $this->assertTrue($result[0]->keepOrder);
    }

    public function test_apply_route_attributes_sets_keep_order_from_class_attribute(): void
    {
        config()->set('router.convention', 'attribute_or_get');

        $definition = $this->makeDefinition(ClassKeepOrderController::class, 'update');

        $result = (new ApplyRouteAttributes)->handle([$definition], function (array $definitions) {
            return (new BuildUri)->handle($definitions, fn (array $definitions) => $definitions);
        });

        $this->assertTrue($result[0]->keepOrder);
        $this->assertSame('default/update/{section}/{id}', $result[0]->uri);
    }

    public function test_apply_route_attributes_supports_absolute_class_and_method_overrides(): void
    {
        $classDefinition = $this->makeDefinition(AbsoluteClassController::class, 'index');
        $methodDefinition = $this->makeDefinition(AbsoluteMethodController::class, 'show');

        $result = (new ApplyRouteAttributes)->handle([$classDefinition, $methodDefinition], fn (array $definitions) => $definitions);

        $this->assertTrue($result[0]->absolute);
        $this->assertTrue($result[1]->absolute);
    }

    public function test_apply_route_attributes_supports_scoped_binding_options(): void
    {
        $definition = $this->makeDefinition(ScopedBindingController::class, 'show');

        $result = (new ApplyRouteAttributes)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertTrue($result[0]->scopeBindings);
        $this->assertTrue($result[0]->withoutScopedBindings);
    }

    public function test_build_uri_generates_segments_and_appends_bindings_when_keep_order_is_disabled(): void
    {
        config()->set('router.convention', 'attribute_or_get');

        $definition = $this->makeDefinition(
            BuildUriProfileController::class,
            'editSection',
            $this->fixtureFile('RouteDiscovery/Controllers/User/ProfileController.php', 'User/ProfileController.php')
        );

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('user/{user}/profile/{section}/edit-section', $result[0]->uri);
    }

    public function test_build_uri_preserves_existing_uri(): void
    {
        $definition = $this->makeDefinition(RouteAttributedController::class, 'store');
        $definition->uri = 'already-defined';

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('already-defined', $result[0]->uri);
    }

    public function test_build_uri_supports_an_absolute_method_override(): void
    {
        $definition = $this->makeDefinition(RouteAttributedController::class, 'store');
        $definition->methodUri = '/teams/{team}/members/{member}/settings';
        $definition->absolute = true;

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('teams/{team}/members/{member}/settings', $result[0]->uri);
    }

    public function test_build_uri_discovers_backed_enum_parameters(): void
    {
        config()->set('router.convention', 'attribute_or_get');
        $definition = $this->makeDefinition(EnumParameterController::class, 'show');

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('default/{status}/show', $result[0]->uri);
    }

    public function test_build_uri_supports_nullable_optional_parameters(): void
    {
        config()->set('router.convention', 'attribute_or_get');
        $definition = $this->makeDefinition(OptionalParameterController::class, 'show');

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('default/show/{id?}', $result[0]->uri);
    }

    public function test_build_uri_preserves_explicit_custom_route_keys(): void
    {
        config()->set('router.convention', 'attribute_or_get');
        $definition = $this->makeDefinition(CustomKeyController::class, 'show');

        $result = (new BuildUri)->handle(
            (new ApplyRouteAttributes)->handle([$definition], fn (array $definitions) => $definitions),
            fn (array $definitions) => $definitions,
        );

        $this->assertSame('default/users/{user:slug}', $result[0]->uri);
    }

    public function test_build_uri_respects_keep_order(): void
    {
        config()->set('router.convention', 'attribute_or_get');

        $definition = $this->makeDefinition(
            BuildUriKeepOrderController::class,
            'editSection',
            $this->fixtureFile('RouteDiscovery/Controllers/DefaultController.php', 'BuildUriKeepOrderController.php')
        );
        $definition->keepOrder = true;

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('build-uri-keep-order/edit-section/{user}/{section}', $result[0]->uri);
    }

    public function test_build_uri_omits_index_controller_and_index_method_segments(): void
    {
        config()->set('router.convention', 'attribute_or_get');

        $definition = $this->makeDefinition(
            BuildUriIndexControllerFixture::class,
            'index',
            $this->fixtureFile('RouteDiscovery/Controllers/DefaultController.php', 'IndexController.php')
        );

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('', $result[0]->uri);
    }

    public function test_build_uri_throws_for_index_folder(): void
    {
        $definition = $this->makeDefinition(
            BuildUriIndexFolderController::class,
            'show',
            $this->fixtureFile('Diagnostics/Controllers/Index/ProfileController.php', 'Index/ProfileController.php')
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Index folder is not allowed in route discovery');

        (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);
    }

    public function test_build_uri_throws_when_not_enough_parameters_are_available_for_placeholders(): void
    {
        config()->set('router.convention', 'attribute_or_get');

        $definition = $this->makeDefinition(
            BuildUriMissingBindingController::class,
            'show',
            $this->fixtureFile('RouteDiscovery/Controllers/User/ProfileController.php', 'User/ProfileController.php')
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough parameters to bind');

        (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);
    }

    public function test_build_http_verb_uses_prefix_convention(): void
    {
        config()->set('router.convention', 'prefix');
        $definition = $this->makeDefinition(PrefixController::class, 'deleteArchive');

        $result = (new BuildHttpVerb)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('DELETE', $result[0]->httpVerb);
    }

    public function test_build_http_verb_does_not_match_lowercase_words_that_start_with_a_verb(): void
    {
        config()->set('router.convention', 'prefix');
        $definition = $this->makeDefinition(PrefixController::class, 'getaway');

        $result = (new BuildHttpVerb)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('', $result[0]->httpVerb);
        $this->assertFalse($result[0]->isDiscoverable);
        $this->assertStringContainsString('does not match the prefix routing convention', $result[0]->skipReason);
    }

    public function test_build_http_verb_uses_map_and_defaults_to_get(): void
    {
        config()->set('router.convention', 'attribute_or_get');
        config()->set('router.http_methods_map', [
            'store' => ['post', 'put'],
        ]);

        $store = $this->makeDefinition(RouteAttributedController::class, 'store');
        $index = $this->makeDefinition(RouteAttributedController::class, 'index');

        $result = (new BuildHttpVerb)->handle([$store, $index], fn (array $definitions) => $definitions);

        $this->assertSame(['POST', 'PUT'], $result[0]->httpVerb);
        $this->assertSame('GET', $result[1]->httpVerb);
    }

    public function test_attribute_or_get_does_not_infer_a_verb_from_the_method_name(): void
    {
        config()->set('router.convention', 'attribute_or_get');

        $definition = $this->makeDefinition(PrefixController::class, 'postStore');

        $result = (new BuildHttpVerb)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('GET', $result[0]->httpVerb);
    }

    public function test_build_http_verb_supports_scalar_http_method_map_entries(): void
    {
        config()->set('router.convention', 'attribute_or_get');
        config()->set('router.http_methods_map', [
            'destroy' => 'delete',
        ]);

        $definition = $this->makeDefinition(ScalarVerbMapController::class, 'destroy');

        $result = (new BuildHttpVerb)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('DELETE', $result[0]->httpVerb);
    }

    public function test_build_http_verb_does_not_override_existing_http_verb(): void
    {
        config()->set('router.convention', 'prefix');
        $definition = $this->makeDefinition(PrefixController::class, 'deleteArchive');
        $definition->httpVerb = ['PATCH'];

        $result = (new BuildHttpVerb)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['PATCH'], $result[0]->httpVerb);
    }

    public function test_build_route_name_normalizes_nested_controller_names(): void
    {
        config()->set('router.convention', 'prefix');

        $definition = $this->makeDefinition(
            BuildRouteNameFixtureController::class,
            'getShowAccount',
            $this->fixtureFile('RouteDiscovery/Controllers/Admin/UsersController.php', 'Admin/UsersController.php')
        );

        $result = (new BuildRouteName)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('admin.users.show-account', $result[0]->name);
    }

    public function test_apply_middleware_merges_parent_class_and_method_middleware_uniquely(): void
    {
        $definition = $this->makeDefinition(MiddlewareController::class, 'update');
        $definition->parentAttributes = [new RouteAttribute(middleware: ['auth', 'verified'])];

        $result = (new ApplyMiddleware)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['auth', 'verified', 'bindings', 'audit'], $result[0]->middleware);
    }

    public function test_apply_middleware_ignores_parent_and_class_middleware_when_requested_on_method(): void
    {
        $definition = $this->makeDefinition(MethodIgnoresParentMiddlewareController::class, 'update');
        $definition->parentAttributes = [new RouteAttribute(middleware: ['auth'])];

        $result = (new ApplyMiddleware)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['audit'], $result[0]->middleware);
    }

    public function test_apply_middleware_ignores_only_parent_middleware_when_requested_on_class(): void
    {
        $definition = $this->makeDefinition(ClassIgnoresParentMiddlewareController::class, 'update');
        $definition->parentAttributes = [new RouteAttribute(middleware: ['auth'])];

        $result = (new ApplyMiddleware)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['bindings', 'audit'], $result[0]->middleware);
    }

    public function test_apply_where_constraints_merges_parent_class_and_method_constraints_with_last_write_wins(): void
    {
        $definition = $this->makeDefinition(WhereController::class, 'show');
        $definition->parentAttributes = [
            new Where('account', '[0-9]+'),
            new Where('team', '[A-Z]+'),
        ];

        $result = (new ApplyWhereConstraints)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame([
            'account' => '[a-z]+',
            'team' => '[A-Z]+',
            'member' => '[0-9]+',
        ], $result[0]->wheres);
    }

    private function makeDefinition(string $className, string $methodName, ?SplFileInfo $file = null): RouteDefinition
    {
        return new RouteDefinition(
            file: $file ?? $this->fixtureFile('RouteDiscovery/Controllers/DefaultController.php', 'DefaultController.php'),
            class: new \ReflectionClass($className),
            method: new \ReflectionMethod($className, $methodName),
            fullyQualifiedClassName: $className,
        );
    }

    private function fixtureFile(string $relativeToFixtures, string $relativePathname): SplFileInfo
    {
        $path = $this->fixturePath($relativeToFixtures);

        return new SplFileInfo($path, dirname($relativePathname) === '.' ? '' : dirname($relativePathname), $relativePathname);
    }
}

#[RouteAttribute(middleware: ['base'])]
class InheritanceBaseController extends Controller {}

#[Where('account', '[0-9]+')]
#[RouteAttribute(middleware: ['mid'])]
class InheritanceMidController extends InheritanceBaseController {}

class InheritanceChildController extends InheritanceMidController
{
    public function index(): void {}
}

#[DoNotDiscover]
class DoNotDiscoverController
{
    public function index(): void {}
}

#[LocalOnly]
class LocalOnlyController
{
    #[LocalOnly]
    public function show(): void {}
}

class RouteAttributedController
{
    public function index(): void {}

    #[RouteAttribute(uri: 'custom-uri', method: ['patch', 'delete'], keepOrder: true)]
    public function store(): void {}
}

#[RouteAttribute(keepOrder: true)]
class ClassKeepOrderController
{
    public function update(string $section, int $id): void {}
}

class BuildUriProfileController
{
    public function editSection(User $user, string $section): void {}
}

class BuildUriKeepOrderController
{
    public function editSection(User $user, string $section): void {}
}

class BuildUriIndexControllerFixture
{
    public function index(): void {}
}

class BuildUriIndexFolderController
{
    public function show(): void {}
}

class BuildUriMissingBindingController
{
    public function show(): void {}
}

class PrefixController
{
    public function deleteArchive(): void {}

    public function postStore(): void {}

    public function getaway(): void {}
}

class ScalarVerbMapController
{
    public function destroy(): void {}
}

class BuildRouteNameFixtureController
{
    public function getShowAccount(): void {}
}

#[RouteAttribute(middleware: ['bindings'])]
class MiddlewareController
{
    #[RouteAttribute(middleware: ['audit', 'auth'])]
    public function update(): void {}
}

#[RouteAttribute(middleware: ['bindings'])]
class MethodIgnoresParentMiddlewareController
{
    #[IgnoreParentMiddleware]
    #[RouteAttribute(middleware: ['audit'])]
    public function update(): void {}
}

#[IgnoreParentMiddleware]
#[RouteAttribute(middleware: ['bindings'])]
class ClassIgnoresParentMiddlewareController
{
    #[RouteAttribute(middleware: ['audit'])]
    public function update(): void {}
}

#[Where('account', '[a-z]+')]
class WhereController
{
    #[Where('member', '[0-9]+')]
    public function show(): void {}
}

#[RouteAttribute(uri: 'teams/{team}', absolute: true)]
class AbsoluteClassController
{
    public function index(): void {}
}

class AbsoluteMethodController
{
    #[RouteAttribute(uri: '/teams/{team}/members/{member}', absolute: true)]
    public function show(string $team, string $member): void {}
}

#[RouteAttribute(scopeBindings: true, withoutScopedBindings: true)]
class ScopedBindingController
{
    public function show(): void {}
}

class OptionalParameterController
{
    public function show(?int $id = null): void {}
}

class CustomKeyController
{
    #[RouteAttribute(uri: 'users/{user:slug}')]
    public function show(User $user): void {}
}

enum RouteStatus: string
{
    case ACTIVE = 'active';
}

class EnumParameterController
{
    public function show(RouteStatus $status): void {}
}
