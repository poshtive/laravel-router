<?php

namespace Tests\Unit;

use Poshtive\Router\RouteDefinition;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

class RouteDefinitionTest extends TestCase
{
    public function test_it_reports_descriptor_and_default_values(): void
    {
        $definition = $this->makeDefinition(RouteDefinitionFixtureController::class, 'index');

        $this->assertSame(RouteDefinitionFixtureController::class.'::index', $definition->descriptor());
        $this->assertSame([], $definition->getHttpVerbs());
        $this->assertSame('/', $definition->getRegisteredUri());
    }

    public function test_it_marks_definition_as_skipped(): void
    {
        $definition = $this->makeDefinition(RouteDefinitionFixtureController::class, 'index');

        $definition->markSkipped('skip reason');

        $this->assertFalse($definition->isDiscoverable);
        $this->assertSame('skip reason', $definition->skipReason);
    }

    public function test_it_normalizes_http_verbs(): void
    {
        $definition = $this->makeDefinition(RouteDefinitionFixtureController::class, 'index');
        $definition->httpVerb = ['get', 'GET', 'post'];

        $this->assertSame(['GET', 'POST'], $definition->getHttpVerbs());

        $definition->httpVerb = 'patch';

        $this->assertSame(['PATCH'], $definition->getHttpVerbs());
    }

    public function test_it_calculates_priority_score_from_uri_complexity(): void
    {
        $definition = $this->makeDefinition(RouteDefinitionFixtureController::class, 'index');
        $definition->uri = 'users/{user}/comments/{comment}';

        $this->assertSame(1969, $definition->getPriorityScore());
    }

    public function test_it_builds_method_name_without_prefix_convention(): void
    {
        config()->set('router.convention', 'attribute_or_get');

        $definition = $this->makeDefinition(RouteDefinitionFixtureController::class, 'postStoreRecord');

        $this->assertSame('post-store-record', $definition->getMethodName());
    }

    public function test_it_strips_http_verb_prefix_when_using_prefix_convention(): void
    {
        config()->set('router.convention', 'prefix');

        $definition = $this->makeDefinition(RouteDefinitionFixtureController::class, 'postStoreRecord');

        $this->assertSame('store-record', $definition->getMethodName());
    }

    public function test_it_throws_when_prefix_method_name_becomes_empty(): void
    {
        config()->set('router.convention', 'prefix');

        $definition = $this->makeDefinition(RouteDefinitionFixtureController::class, 'get');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Method name cannot be empty after stripping verb prefix.');

        $definition->getMethodName();
    }

    private function makeDefinition(string $className, string $methodName): RouteDefinition
    {
        return new RouteDefinition(
            file: new SplFileInfo(__FILE__, '', 'RouteDefinitionFixtureController.php'),
            class: new \ReflectionClass($className),
            method: new \ReflectionMethod($className, $methodName),
            fullyQualifiedClassName: $className,
        );
    }
}

class RouteDefinitionFixtureController
{
    public function index(): void {}

    public function postStoreRecord(): void {}

    public function get(): void {}
}
