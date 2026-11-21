<?php

namespace Tests\Unit;

use Illuminate\Routing\Controller;
use Poshtive\Router\Attributes\Delete;
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Get;
use Poshtive\Router\Attributes\IgnoreParentMiddleware;
use Poshtive\Router\Attributes\LocalOnly;
use Poshtive\Router\Attributes\Middleware;
use Poshtive\Router\Attributes\Patch;
use Poshtive\Router\Attributes\Post;
use Poshtive\Router\Attributes\Put;
use Poshtive\Router\Attributes\Resource;
use Poshtive\Router\Attributes\ScopeBindings;
use Poshtive\Router\Attributes\Where;
use Poshtive\Router\Attributes\WithoutScopedBindings;
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
        $this->assertInstanceOf(Middleware::class, $result[0]->parentAttributes[0]);
        $this->assertInstanceOf(Middleware::class, $result[0]->parentAttributes[1]);
        $this->assertInstanceOf(Where::class, $result[0]->parentAttributes[2]);
        $this->assertSame(['base'], $result[0]->parentAttributes[0]->middleware);
        $this->assertSame(['mid'], $result[0]->parentAttributes[1]->middleware);
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

    public function test_http_method_attributes_set_verbs_uri_and_name(): void
    {
        $definition = $this->makeDefinition(RouteAttributedController::class, 'store');

        $result = (new ApplyRouteAttributes)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['PATCH', 'DELETE'], $result[0]->httpVerb);
        $this->assertSame('custom-uri', $result[0]->methodUri);
        $this->assertSame('custom.store', $result[0]->methodNameOverride);
    }

    public function test_each_http_method_attribute_contributes_its_verb(): void
    {
        $definitions = array_map(
            fn (string $method) => $this->makeDefinition(VerbAttributeController::class, $method),
            ['viewed', 'created', 'replaced', 'patched', 'deleted', 'duplicated'],
        );

        $result = (new ApplyRouteAttributes)->handle($definitions, fn (array $definitions) => $definitions);

        $this->assertSame(
            [['GET'], ['POST'], ['PUT'], ['PATCH'], ['DELETE'], ['POST']],
            array_map(fn (RouteDefinition $definition) => $definition->httpVerb, $result),
        );
    }

    public function test_http_method_attributes_reject_conflicting_uri_or_name(): void
    {
        $uri = $this->makeDefinition(ConflictingVerbAttributeController::class, 'uri');
        $name = $this->makeDefinition(ConflictingVerbAttributeController::class, 'name');

        (new ApplyRouteAttributes)->handle([$uri, $name], fn (array $definitions) => $definitions);

        $this->assertStringContainsString('conflicting uri or name', (string) $uri->invalidReason);
        $this->assertStringContainsString('conflicting uri or name', (string) $name->invalidReason);
    }

    public function test_leading_slash_uris_ignore_folder_segments(): void
    {
        $classDefinition = $this->makeDefinition(AbsoluteClassController::class, 'index');
        $methodDefinition = $this->makeDefinition(AbsoluteMethodController::class, 'show');

        $result = (new ApplyRouteAttributes)->handle(
            [$classDefinition, $methodDefinition],
            fn (array $definitions) => (new BuildUri)->handle($definitions, fn (array $definitions) => $definitions),
        );

        $this->assertSame('teams/{team}', $result[0]->uri);
        $this->assertSame('teams/{team}/members/{member}', $result[1]->uri);
    }

    public function test_resource_attribute_overrides_the_class_segment_and_name(): void
    {
        $definition = $this->makeDefinition(ResourceController::class, 'index');

        $result = (new ApplyRouteAttributes)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('people', $result[0]->classUri);
        $this->assertSame('person', $result[0]->className);
    }

    public function test_scoped_binding_attributes_resolve_with_method_precedence(): void
    {
        $definitions = [
            $this->makeDefinition(ScopedBindingController::class, 'index'),
            $this->makeDefinition(ScopedBindingController::class, 'show'),
            $this->makeDefinition(DefaultScopedBindingController::class, 'show'),
        ];

        $result = (new ApplyRouteAttributes)->handle($definitions, fn (array $definitions) => $definitions);

        $this->assertTrue($result[0]->scopeBindings);
        $this->assertFalse($result[1]->scopeBindings);
        $this->assertNull($result[2]->scopeBindings);
    }

    public function test_scoped_binding_attributes_cannot_be_combined_on_one_target(): void
    {
        $method = $this->makeDefinition(ConflictingScopedBindingController::class, 'show');
        $class = $this->makeDefinition(ConflictingClassScopedBindingController::class, 'show');

        (new ApplyRouteAttributes)->handle([$method, $class], fn (array $definitions) => $definitions);

        $this->assertStringContainsString('#[ScopeBindings] and #[WithoutScopedBindings]', (string) $method->invalidReason);
        $this->assertStringContainsString('#[ScopeBindings] and #[WithoutScopedBindings]', (string) $class->invalidReason);
    }

    public function test_build_uri_generates_segments_and_places_the_first_binding_before_the_method_segment(): void
    {
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

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('teams/{team}/members/{member}/settings', $result[0]->uri);
    }

    public function test_build_uri_discovers_backed_enum_parameters(): void
    {
        $definition = $this->makeDefinition(EnumParameterController::class, 'show');

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('default/{status}', $result[0]->uri);
    }

    public function test_build_uri_rejects_int_backed_enum_parameters(): void
    {
        $definition = $this->makeDefinition(IntEnumParameterController::class, 'show');

        (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertFalse($definition->isDiscoverable);
        $this->assertStringContainsString('not string-backed', $definition->invalidReason);
    }

    public function test_build_uri_rejects_unit_enum_parameters(): void
    {
        $definition = $this->makeDefinition(UnitEnumParameterController::class, 'show');

        (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertFalse($definition->isDiscoverable);
        $this->assertStringContainsString('unit enum', $definition->invalidReason);
    }

    public function test_build_uri_supports_nullable_optional_parameters(): void
    {
        $definition = $this->makeDefinition(OptionalParameterController::class, 'show');

        $result = (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('default/{id?}', $result[0]->uri);
    }

    public function test_build_uri_preserves_explicit_custom_route_keys(): void
    {
        $definition = $this->makeDefinition(CustomKeyController::class, 'show');

        $result = (new BuildUri)->handle(
            (new ApplyRouteAttributes)->handle([$definition], fn (array $definitions) => $definitions),
            fn (array $definitions) => $definitions,
        );

        $this->assertSame('default/users/{user:slug}', $result[0]->uri);
    }

    public function test_build_uri_omits_index_controller_and_index_method_segments(): void
    {
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

    public function test_build_uri_marks_routes_invalid_when_not_enough_parameters_are_available_for_placeholders(): void
    {
        $definition = $this->makeDefinition(
            BuildUriMissingBindingController::class,
            'show',
            $this->fixtureFile('RouteDiscovery/Controllers/User/ProfileController.php', 'User/ProfileController.php')
        );

        (new BuildUri)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertFalse($definition->isDiscoverable);
        $this->assertStringContainsString('Not enough parameters to bind', $definition->invalidReason);
    }

    public function test_build_http_verb_follows_resource_conventions_and_defaults_to_get(): void
    {
        $definitions = array_map(
            fn (string $method) => $this->makeDefinition(ResourceActionController::class, $method),
            ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'postStore'],
        );

        $result = (new BuildHttpVerb)->handle($definitions, fn (array $definitions) => $definitions);

        $this->assertSame(
            [['GET'], ['GET'], ['POST'], ['GET'], ['GET'], ['PUT', 'PATCH'], ['DELETE'], ['GET']],
            array_map(fn (RouteDefinition $definition) => $definition->httpVerb, $result),
        );
    }

    public function test_build_http_verb_does_not_override_existing_http_verb(): void
    {
        $definition = $this->makeDefinition(ResourceActionController::class, 'destroy');
        $definition->httpVerb = ['PATCH'];

        $result = (new BuildHttpVerb)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['PATCH'], $result[0]->httpVerb);
    }

    public function test_build_http_verb_skips_undiscoverable_definitions(): void
    {
        $definition = $this->makeDefinition(ResourceActionController::class, 'store');
        $definition->markSkipped('skipped');

        $result = (new BuildHttpVerb)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('', $result[0]->httpVerb);
    }

    public function test_build_uri_follows_resource_conventions(): void
    {
        $definitions = array_map(
            fn (string $method) => $this->makeDefinition(ResourceActionController::class, $method),
            ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'publish'],
        );

        $result = (new BuildUri)->handle($definitions, fn (array $definitions) => $definitions);

        $this->assertSame([
            'default',
            'default/create',
            'default',
            'default/{user}',
            'default/{user}/edit',
            'default/{user}',
            'default/{user}',
            'default/{user}/publish',
        ], array_map(fn (RouteDefinition $definition) => $definition->uri, $result));
    }

    public function test_build_route_name_normalizes_nested_controller_names(): void
    {
        $definition = $this->makeDefinition(
            BuildRouteNameFixtureController::class,
            'showAccount',
            $this->fixtureFile('RouteDiscovery/Controllers/Admin/UsersController.php', 'Admin/UsersController.php')
        );

        $result = (new BuildRouteName)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame('admin.users.show-account', $result[0]->name);
    }

    public function test_apply_middleware_merges_parent_class_and_method_middleware_uniquely(): void
    {
        $definition = $this->makeDefinition(MiddlewareController::class, 'update');
        $definition->parentAttributes = [new Middleware('auth', 'verified')];

        $result = (new ApplyMiddleware)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['auth', 'verified', 'bindings', 'audit'], $result[0]->middleware);
    }

    public function test_repeatable_middleware_attributes_flatten_in_declaration_order(): void
    {
        $definition = $this->makeDefinition(RepeatableMiddlewareController::class, 'update');

        $result = (new ApplyMiddleware)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['auth', 'verified', 'can:update,post', 'throttle'], $result[0]->middleware);
    }

    public function test_apply_middleware_ignores_parent_and_class_middleware_when_requested_on_method(): void
    {
        $definition = $this->makeDefinition(MethodIgnoresParentMiddlewareController::class, 'update');
        $definition->parentAttributes = [new Middleware('auth')];

        $result = (new ApplyMiddleware)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame(['audit'], $result[0]->middleware);
    }

    public function test_apply_middleware_ignores_only_parent_middleware_when_requested_on_class(): void
    {
        $definition = $this->makeDefinition(ClassIgnoresParentMiddlewareController::class, 'update');
        $definition->parentAttributes = [new Middleware('auth')];

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

    public function test_apply_where_constraints_supports_repeatable_class_and_method_attributes(): void
    {
        $definition = $this->makeDefinition(RepeatableWhereController::class, 'show');

        $result = (new ApplyWhereConstraints)->handle([$definition], fn (array $definitions) => $definitions);

        $this->assertSame([
            'account' => '[0-9]+',
            'team' => '[A-Z]+',
            'member' => '[a-z]+',
            'region' => '[a-z]{2}',
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
        // Finder reports relative paths with the platform separator.
        $relativePathname = str_replace('/', DIRECTORY_SEPARATOR, $relativePathname);

        return new SplFileInfo($path, dirname($relativePathname) === '.' ? '' : dirname($relativePathname), $relativePathname);
    }
}

#[Middleware('base')]
class InheritanceBaseController extends Controller {}

#[Where('account', '[0-9]+')]
#[Middleware('mid')]
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

    #[Patch('custom-uri', name: 'custom.store')]
    #[Delete]
    public function store(): void {}
}

class BuildUriProfileController
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

class ResourceActionController
{
    public function index(): void {}

    public function create(): void {}

    public function store(): void {}

    public function show(User $user): void {}

    public function edit(User $user): void {}

    public function update(User $user): void {}

    public function destroy(User $user): void {}

    public function publish(User $user): void {}

    public function postStore(): void {}
}

class BuildRouteNameFixtureController
{
    public function showAccount(): void {}
}

#[Middleware('bindings')]
class MiddlewareController
{
    #[Middleware('audit', 'auth')]
    public function update(): void {}
}

#[Middleware('bindings')]
class MethodIgnoresParentMiddlewareController
{
    #[IgnoreParentMiddleware]
    #[Middleware('audit')]
    public function update(): void {}
}

#[IgnoreParentMiddleware]
#[Middleware('bindings')]
class ClassIgnoresParentMiddlewareController
{
    #[Middleware('audit')]
    public function update(): void {}
}

#[Where('account', '[a-z]+')]
class WhereController
{
    #[Where('member', '[0-9]+')]
    public function show(): void {}
}

#[Where('account', '[0-9]+')]
#[Where('team', '[A-Z]+')]
class RepeatableWhereController
{
    #[Where('member', '[a-z]+')]
    #[Where('region', '[a-z]{2}')]
    public function show(): void {}
}

#[Resource('/teams/{team}')]
class AbsoluteClassController
{
    public function index(string $team): void {}
}

class AbsoluteMethodController
{
    #[Get('/teams/{team}/members/{member}')]
    public function show(string $team, string $member): void {}
}

#[ScopeBindings]
class ScopedBindingController
{
    public function index(): void {}

    #[WithoutScopedBindings]
    public function show(): void {}
}

class DefaultScopedBindingController
{
    public function show(): void {}
}

class ConflictingScopedBindingController
{
    #[ScopeBindings]
    #[WithoutScopedBindings]
    public function show(): void {}
}

#[ScopeBindings]
#[WithoutScopedBindings]
class ConflictingClassScopedBindingController
{
    public function show(): void {}
}

#[Resource('people', name: 'person')]
class ResourceController
{
    public function index(): void {}
}

class VerbAttributeController
{
    #[Get]
    public function viewed(): void {}

    #[Post]
    public function created(): void {}

    #[Put]
    public function replaced(): void {}

    #[Patch]
    public function patched(): void {}

    #[Delete]
    public function deleted(): void {}

    #[Post]
    #[Post]
    public function duplicated(): void {}
}

class ConflictingVerbAttributeController
{
    #[Put('first')]
    #[Patch('second')]
    public function uri(): void {}

    #[Put(name: 'first')]
    #[Patch(name: 'second')]
    public function name(): void {}
}

#[Middleware('auth')]
#[Middleware('verified')]
class RepeatableMiddlewareController
{
    #[Middleware('can:update,post', 'auth')]
    #[Middleware('throttle')]
    public function update(): void {}
}

class OptionalParameterController
{
    public function show(?int $id = null): void {}
}

class CustomKeyController
{
    #[Get('users/{user:slug}')]
    public function show(User $user): void {}
}

enum RouteStatus: string
{
    case ACTIVE = 'active';
}

enum RouteStatusInt: int
{
    case ACTIVE = 1;
}

enum RouteStatusUnit
{
    case ACTIVE;
}

class EnumParameterController
{
    public function show(RouteStatus $status): void {}
}

class IntEnumParameterController
{
    public function show(RouteStatusInt $status): void {}
}

class UnitEnumParameterController
{
    public function show(RouteStatusUnit $status): void {}
}
