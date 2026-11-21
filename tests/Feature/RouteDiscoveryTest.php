<?php

namespace Tests\Feature;

use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\RouteDiscoveryManager;
use Poshtive\Router\Discovery\Severity;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Tests\Support\ArrayLogger;
use Tests\TestCase;

class RouteDiscoveryTest extends TestCase
{
    public function test_it_discovers_routes_with_nested_controllers_and_attributes(): void
    {
        $this->discover('RouteDiscovery/Controllers', 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\');

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->mapWithKeys(fn ($route) => [$route->getName() => $route]);

        $this->assertSame('user', $routes['user.index']->uri());
        $this->assertSame(['GET', 'HEAD'], $routes['user.index']->methods());
        $this->assertSame(['auth', 'bindings'], $routes['user.index']->gatherMiddleware());

        $this->assertSame('user/{user}', $routes['user.show']->uri());
        $this->assertSame(['user' => '[0-9]+'], $routes['user.show']->wheres);

        $this->assertSame('user/update/{user}/{section}', $routes['user.update']->uri());
        $this->assertSame(['PUT'], $routes['user.update']->methods());
        $this->assertSame(['auth', 'bindings', 'verified'], $routes['user.update']->gatherMiddleware());

        $this->assertSame('user/{user}/profile/{section}/edit', $routes['user.profile.edit']->uri());
        $this->assertSame(['GET', 'HEAD'], $routes['user.profile.edit']->methods());
    }

    public function test_resource_actions_register_laravel_resource_routes(): void
    {
        $this->discover('Resource/Controllers', 'Tests\\Fixtures\\Resource\\Controllers\\');

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getAction('_laravel_router') !== null)
            ->mapWithKeys(fn ($route) => [$route->getName() => [implode('|', $route->methods()), $route->uri()]]);

        $this->assertEquals([
            'buku.index' => ['GET|HEAD', 'buku'],
            'buku.create' => ['GET|HEAD', 'buku/create'],
            'buku.store' => ['POST', 'buku'],
            'buku.show' => ['GET|HEAD', 'buku/{buku}'],
            'buku.edit' => ['GET|HEAD', 'buku/{buku}/edit'],
            'buku.update' => ['PUT|PATCH', 'buku/{buku}'],
            'buku.destroy' => ['DELETE', 'buku/{buku}'],
            'buku.terbitkan' => ['POST', 'buku/{buku}/terbitkan'],
        ], $routes->all());
    }

    public function test_singleton_resources_follow_from_method_signatures(): void
    {
        $this->discover('Singleton/Controllers', 'Tests\\Fixtures\\Singleton\\Controllers\\');

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getAction('_laravel_router') !== null)
            ->mapWithKeys(fn ($route) => [$route->getName() => [implode('|', $route->methods()), $route->uri()]])
            ->sortKeys();

        $this->assertEquals([
            'device.pairing.destroy' => ['DELETE', 'device/{device}/pairing'],
            'device.pairing.show' => ['GET|HEAD', 'device/{device}/pairing'],
            'device.pairing.store' => ['POST', 'device/{device}/pairing'],
            'device.show' => ['GET|HEAD', 'device/{device}'],
            'profile.edit' => ['GET|HEAD', 'profile/edit'],
            'profile.show' => ['GET|HEAD', 'profile'],
            'profile.update' => ['PUT|PATCH', 'profile'],
        ], $routes->all());
    }

    public function test_two_level_nested_resources_bind_every_parent(): void
    {
        $this->discover('DeepNesting/Controllers', 'Tests\\Fixtures\\DeepNesting\\Controllers\\');

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getAction('_laravel_router') !== null)
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->uri()])
            ->sortKeys();

        $this->assertEquals([
            'user.post.comment.index' => 'user/{user}/post/{post}/comment',
            'user.post.comment.show' => 'user/{user}/post/{post}/comment/{comment}',
            'user.post.show' => 'user/{user}/post/{post}',
            'user.show' => 'user/{user}',
        ], $routes->all());
    }

    public function test_groups_can_opt_into_pluralized_resource_segments(): void
    {
        app(RouteDiscoveryManager::class)->discover([
            'test' => [
                'paths' => [$this->fixturePath('DeepNesting/Controllers')],
                'namespace' => 'Tests\\Fixtures\\DeepNesting\\Controllers\\',
                'pluralize' => true,
            ],
        ]);

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getAction('_laravel_router') !== null)
            ->mapWithKeys(fn ($route) => [$route->getName() => $route->uri()])
            ->sortKeys();

        $this->assertEquals([
            'users.posts.comments.index' => 'users/{user}/posts/{post}/comments',
            'users.posts.comments.show' => 'users/{user}/posts/{post}/comments/{comment}',
            'users.posts.show' => 'users/{user}/posts/{post}',
            'users.show' => 'users/{user}',
        ], $routes->all());
    }

    public function test_resource_attribute_overrides_a_pluralized_segment(): void
    {
        app(RouteDiscoveryManager::class)->discover([
            'test' => [
                'paths' => [$this->fixturePath('Configured/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Configured\\Controllers\\',
                'pluralize' => true,
            ],
        ]);

        $route = app('router')->getRoutes()->getByName('accounts.profiles.index');

        $this->assertNotNull($route);
        $this->assertSame('accounts/{account}/profiles', $route->uri());
    }

    public function test_static_resource_segments_are_matched_before_member_placeholders(): void
    {
        $this->discover('Resource/Controllers', 'Tests\\Fixtures\\Resource\\Controllers\\');

        $this->get('/buku/create')->assertSee('create');
        $this->get('/buku/42')->assertSee('show 42');
        $this->patch('/buku/42')->assertSee('update 42');
        $this->post('/buku/42/terbitkan')->assertSee('terbitkan 42');
    }

    public function test_method_uri_override_keeps_nested_child_binding_before_the_method_segment(): void
    {
        $this->discover('NestedBinding/Controllers', 'Tests\\Fixtures\\NestedBinding\\Controllers\\');

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->keyBy(fn ($route) => $route->getName());

        $this->assertSame('user/{user}/profile/{profile}/settings', $routes['user.profile.edit-profile']->uri());
    }

    public function test_class_absolute_uri_bypasses_nested_controller_convention(): void
    {
        $this->discover('Absolute/Controllers', 'Tests\\Fixtures\\Absolute\\Controllers\\');

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'AbsoluteController'));

        $this->assertNotNull($route);
        $this->assertSame('teams/{team}/members/{member}/settings', $route->uri());
    }

    public function test_unbalanced_uri_braces_are_reported_and_skipped_in_non_strict_mode(): void
    {
        config()->set('router.strict', false);

        $logger = new ArrayLogger;
        app()->instance('log', $logger);

        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'invalid-uri' => [
                'paths' => [$this->fixturePath('InvalidUri/Controllers')],
                'namespace' => 'Tests\\Fixtures\\InvalidUri\\Controllers\\',
            ],
        ]);

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'BrokenController'));

        $this->assertNull($route);
        $this->assertStringContainsString('Invalid URI', implode("\n", $logger->warningMessages));
    }

    public function test_skipped_routes_are_reported_as_info_without_logging(): void
    {
        $logger = new ArrayLogger;
        app()->instance('log', $logger);

        $this->discover('Diagnostics/Controllers', 'Tests\\Fixtures\\Diagnostics\\Controllers\\');

        $skipped = array_values(array_filter(
            app(RouteDiscoveryManager::class)->diagnostics(),
            fn (Diagnostic $diagnostic) => $diagnostic->code === 'route_skipped',
        ));

        $this->assertCount(2, $skipped);
        $this->assertSame([Severity::Info, Severity::Info], array_map(fn (Diagnostic $diagnostic) => $diagnostic->severity, $skipped));
        $this->assertStringContainsString('DoNotDiscover', $skipped[0]->message.$skipped[1]->message);
        $this->assertStringContainsString('LocalOnly', $skipped[0]->message.$skipped[1]->message);
        $this->assertSame([], $logger->infoMessages);
        $this->assertSame([], array_filter($logger->warningMessages, fn (string $message) => str_contains($message, 'Skipped')));
    }

    public function test_strict_mode_defaults_to_on_in_local_and_testing_environments(): void
    {
        $this->assertTrue(app(RouteDiscoveryManager::class)->strict());

        app()->detectEnvironment(fn () => 'local');
        $this->assertTrue(app(RouteDiscoveryManager::class)->strict());

        app()->detectEnvironment(fn () => 'production');
        $this->assertFalse(app(RouteDiscoveryManager::class)->strict());

        config()->set('router.strict', true);
        $this->assertTrue(app(RouteDiscoveryManager::class)->strict());
    }

    public function test_invalid_routes_throw_by_default_in_the_testing_environment(): void
    {
        $this->expectException(RouteDiscoveryException::class);

        $this->discover('Invalid/Controllers', 'Tests\\Fixtures\\Invalid\\Controllers\\');
    }

    public function test_non_strict_discovery_logs_warnings_with_structured_context(): void
    {
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $this->discover('Invalid/Controllers', 'Tests\\Fixtures\\Invalid\\Controllers\\');

        $this->assertCount(1, $logger->warningMessages);
        $this->assertStringStartsWith('[laravel-router] ', $logger->warningMessages[0]);
        $this->assertSame('route_invalid', $logger->warningContexts[0]['code']);
        $this->assertSame('error', $logger->warningContexts[0]['severity']);
    }

    public function test_nested_uri_overrides_are_scoped_to_the_current_controller(): void
    {
        config()->set('router.strict', true);

        $this->discover('Conflicts/Controllers', 'Tests\\Fixtures\\Conflicts\\Controllers\\');

        $routes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());
        $this->assertSame('conflict', $routes['conflict.index']->uri());
        $this->assertSame('admin/conflict', $routes['admin.conflict.index']->uri());
    }

    public function test_nested_uri_overrides_do_not_report_false_duplicate_signatures(): void
    {
        $logger = new ArrayLogger;

        app()->instance('log', $logger);
        config()->set('router.strict', false);

        $this->discover('Conflicts/Controllers', 'Tests\\Fixtures\\Conflicts\\Controllers\\');

        $this->assertCount(0, $logger->warningMessages);
    }

    public function test_duplicate_routes_are_detected_across_groups_and_registered_once(): void
    {
        $logger = new ArrayLogger;
        app()->instance('log', $logger);
        config()->set('router.strict', false);

        app(RouteDiscoveryManager::class)->discover([
            'first' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
            'second' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
        ]);

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\RouteDiscovery'));

        $this->assertCount(4, $routes);
        $this->assertNotEmpty($logger->warningMessages);
    }

    public function test_strict_duplicate_detection_is_global_and_atomic(): void
    {
        config()->set('router.strict', true);

        try {
            app(RouteDiscoveryManager::class)->discover([
                'first' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
                'second' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
            ]);
            $this->fail('Expected duplicate route detection to throw.');
        } catch (RouteDiscoveryException) {
            $route = collect(app('router')->getRoutes()->getRoutes())
                ->first(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\RouteDiscovery'));

            $this->assertNull($route);
        }
    }

    public function test_discovery_is_skipped_when_routes_are_cached(): void
    {
        app()->instance('routes.cached', true);

        app(RouteDiscoveryManager::class)->discover([
            'test' => ['paths' => [$this->fixturePath('RouteDiscovery/Controllers')], 'namespace' => 'Tests\\Fixtures\\RouteDiscovery\\Controllers\\'],
        ]);

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\RouteDiscovery'));

        $this->assertNull($route);
    }

    public function test_invalid_parameter_mapping_is_reported_and_skipped_in_non_strict_mode(): void
    {
        config()->set('router.strict', false);

        $logger = new ArrayLogger;
        app()->instance('log', $logger);

        app(RouteDiscoveryManager::class)->discover([
            'invalid' => [
                'paths' => [$this->fixturePath('Invalid/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Invalid\\Controllers\\',
            ],
        ]);

        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => str_contains((string) $route->getActionName(), 'Tests\\Fixtures\\Invalid\\Controllers\\BrokenController'));

        $this->assertNull($route);
        $this->assertNotEmpty($logger->warningMessages);
        $this->assertStringContainsString('item', implode("\n", $logger->warningMessages));
    }

    public function test_invalid_parameter_mapping_throws_atomically_in_strict_mode(): void
    {
        config()->set('router.strict', true);

        $this->expectException(RouteDiscoveryException::class);

        app(RouteDiscoveryManager::class)->discover([
            'invalid' => [
                'paths' => [$this->fixturePath('Invalid/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Invalid\\Controllers\\',
            ],
        ]);
    }

    public function test_optional_nested_bindings_are_rejected_when_they_are_not_trailing(): void
    {
        config()->set('router.strict', false);

        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'optional-nested' => [
                'paths' => [$this->fixturePath('OptionalNested/Controllers')],
                'namespace' => 'Tests\\Fixtures\\OptionalNested\\Controllers\\',
            ],
        ]);

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getActionName(), 'OptionalNested'));

        $this->assertCount(0, $routes);
        $this->assertStringContainsString('Optional route parameters must be trailing', implode("\n", $manager->diagnostics()));
    }

    public function test_optional_nested_binding_validation_is_atomic_in_strict_mode(): void
    {
        config()->set('router.strict', true);

        $this->expectException(RouteDiscoveryException::class);

        try {
            app(RouteDiscoveryManager::class)->discover([
                'valid' => [
                    'paths' => [$this->fixturePath('Conflicts/Controllers')],
                    'namespace' => 'Tests\\Fixtures\\Conflicts\\Controllers\\',
                ],
                'optional-nested' => [
                    'paths' => [$this->fixturePath('OptionalNested/Controllers')],
                    'namespace' => 'Tests\\Fixtures\\OptionalNested\\Controllers\\',
                ],
            ]);
        } finally {
            $this->assertNull(app('router')->getRoutes()->getByName('conflict.index'));
        }
    }

    public function test_unloadable_controller_files_are_reported_in_diagnostics(): void
    {
        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'diagnostics' => [
                'paths' => [$this->fixturePath('Diagnostics/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Diagnostics\\Controllers\\',
                'patterns' => ['MissingController.php'],
            ],
        ]);

        $this->assertStringContainsString('could not be loaded', implode("\n", $manager->diagnostics()));
    }

    public function test_missing_discovery_path_generates_structured_diagnostic(): void
    {
        app()->forgetInstance(RouteDiscoveryManager::class);

        $nonexistent = $this->fixturePath('NonExistent/Path');

        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'missing' => ['paths' => [$nonexistent]],
        ]);

        $diagnostics = $manager->diagnostics();
        $this->assertCount(1, $diagnostics);
        $this->assertInstanceOf(Diagnostic::class, $diagnostics[0]);
        $this->assertSame('discovery_path_missing', $diagnostics[0]->code);
        $this->assertSame(Severity::Warning, $diagnostics[0]->severity);
        $this->assertSame('missing', $diagnostics[0]->group);
        $this->assertStringContainsString('NonExistent/Path', $diagnostics[0]->path);
        $this->assertSame('Configured discovery path does not exist.', $diagnostics[0]->message);
    }

    public function test_non_string_discovery_path_generates_diagnostic(): void
    {
        app()->forgetInstance(RouteDiscoveryManager::class);

        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'bad' => ['paths' => [42]],
        ]);

        $diagnostics = $manager->diagnostics();
        $this->assertCount(1, $diagnostics);
        $this->assertInstanceOf(Diagnostic::class, $diagnostics[0]);
        $this->assertSame('discovery_path_invalid', $diagnostics[0]->code);
        $this->assertSame('int', $diagnostics[0]->path);
        $this->assertSame('Configured discovery path is not a valid string.', $diagnostics[0]->message);
    }

    public function test_duplicate_path_diagnostics_are_not_repeated(): void
    {
        app()->forgetInstance(RouteDiscoveryManager::class);

        $nonexistent = $this->fixturePath('NonExistent/Path');

        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'dup' => ['paths' => [$nonexistent, $nonexistent]],
        ]);

        $diagnostics = $manager->diagnostics();
        $this->assertCount(1, $diagnostics);
    }

    public function test_diagnostics_are_serializable(): void
    {
        $diagnostic = new Diagnostic('route_invalid', Severity::Error, 'Invalid URI.', 'test', 'UserController.php', 'App\\UserController::show');

        $this->assertSame([
            'code' => 'route_invalid',
            'severity' => 'error',
            'message' => 'Invalid URI.',
            'group' => 'test',
            'path' => 'UserController.php',
            'action' => 'App\\UserController::show',
        ], json_decode((string) json_encode($diagnostic), true));
    }

    public function test_diagnose_command_displays_path_diagnostics(): void
    {
        $nonexistent = $this->fixturePath('NonExistent/Path');

        $manager = app(RouteDiscoveryManager::class);
        $manager->discover([
            'missing' => ['paths' => [$nonexistent]],
        ]);

        $this->artisan('router:diagnose')
            ->assertExitCode(0)
            ->expectsOutputToContain('Configured discovery path does not exist');
    }

    public function test_diagnostics_cast_to_a_readable_string(): void
    {
        $diagnostic = new Diagnostic('discovery_path_missing', Severity::Warning, 'Configured discovery path does not exist.');

        $this->assertSame('[warning] Configured discovery path does not exist.', (string) $diagnostic);
    }

    private function discover(string $path, string $namespace): void
    {
        app(RouteDiscoveryManager::class)->discover([
            'test' => ['paths' => [$this->fixturePath($path)], 'namespace' => $namespace],
        ]);
    }
}
