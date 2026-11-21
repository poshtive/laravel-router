<?php

namespace Tests\Wayfinder;

use Illuminate\Filesystem\Filesystem;
use Laravel\Wayfinder\WayfinderServiceProvider;
use Tests\TestCase;

abstract class WayfinderTestCase extends TestCase
{
    protected string $outputPath;

    protected function setUp(): void
    {
        if (! class_exists(WayfinderServiceProvider::class)) {
            $this->markTestSkipped('Laravel Wayfinder is not installed. Run: composer require --dev laravel/wayfinder');
        }

        parent::setUp();

        $this->outputPath = sys_get_temp_dir().'/laravel-router-wayfinder-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->outputPath);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), WayfinderServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Laravel 12 registers storage.local routes; keep the generated output to discovered routes.
        $app['config']->set('filesystems.disks.local.serve', false);

        $app['config']->set('router.groups', [
            'wayfinder' => [
                'paths' => [$this->fixturePath('Wayfinder/Controllers')],
                'namespace' => 'Tests\\Fixtures\\Wayfinder\\Controllers\\',
            ],
        ]);
    }

    /**
     * Run wayfinder:generate and return every generated file keyed by its relative path.
     *
     * @return array<string, string>
     */
    protected function generate(string $directory): array
    {
        $path = $this->outputPath.'/'.$directory;

        $this->artisan('wayfinder:generate', ['--path' => $path, '--skip-database' => true])->assertExitCode(0);

        $files = [];
        foreach ((new Filesystem)->allFiles($path) as $file) {
            $files[str_replace('\\', '/', $file->getRelativePathname())] = $file->getContents();
        }
        ksort($files);

        return $files;
    }

    /**
     * Extract the runtime route definitions (verbs and URL) from a generated action file.
     *
     * @return array<string, array{methods: list<string>, url: string}>
     */
    protected function definitions(string $typescript): array
    {
        preg_match_all('/^(\w+)\.definition = \{\s*methods: (\[[^\]]*\]),\s*url: \'([^\']*)\',/m', $typescript, $matches, PREG_SET_ORDER);

        $definitions = [];
        foreach ($matches as [, $action, $methods, $url]) {
            $definitions[$action] = ['methods' => json_decode($methods, true), 'url' => $url];
        }
        ksort($definitions);

        return $definitions;
    }
}
