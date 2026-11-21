<?php

namespace Tests\Wayfinder;

class WayfinderGenerationTest extends WayfinderTestCase
{
    private const string ACTIONS = 'actions/Tests/Fixtures/Wayfinder/Controllers/';

    public function test_wayfinder_generates_actions_for_discovered_routes(): void
    {
        $files = $this->generate('live');

        $this->assertSame([
            'destroy' => ['methods' => ['delete'], 'url' => '/user/{team}'],
            'index' => ['methods' => ['get', 'head'], 'url' => '/user'],
            'search' => ['methods' => ['get', 'head'], 'url' => '/user/search/{query?}'],
            'show' => ['methods' => ['get', 'head'], 'url' => '/user/{team}'],
            'store' => ['methods' => ['post'], 'url' => '/user'],
            'update' => ['methods' => ['put', 'patch'], 'url' => '/user/{team}/{status}'],
        ], $this->definitions($files[self::ACTIONS.'UserController.ts']));

        $this->assertSame([
            'edit' => ['methods' => ['get', 'head'], 'url' => '/user/{team}/profile/{profile}/edit'],
        ], $this->definitions($files[self::ACTIONS.'User/ProfileController.ts']));

        $this->assertArrayHasKey('routes/user/index.ts', $files);
        $this->assertArrayHasKey('routes/user/profile/index.ts', $files);
    }

    public function test_wayfinder_output_is_identical_from_the_route_cache(): void
    {
        $live = $this->generate('live');

        try {
            $this->cacheRoutes();
            $this->refreshApplication();

            $this->assertTrue($this->app->routesAreCached());
            $this->assertSame($live, $this->generate('cached'));
        } finally {
            $this->artisan('route:clear')->assertExitCode(0);
        }
    }
}
