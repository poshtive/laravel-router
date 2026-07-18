<?php

namespace Tests\Wayfinder;

use Laravel\Wayfinder\Route;
use Tests\TestCase;

abstract class WayfinderTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Route::class)) {
            $this->markTestSkipped('Laravel Wayfinder is not installed. Run: composer require --dev laravel/wayfinder');
        }
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('router.groups', [
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);
    }
}
