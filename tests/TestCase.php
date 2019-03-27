<?php

namespace Dievelop\LaravelPurge\Tests;

/**
 * Class TestCase
 */
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected $tmpDir;

    protected function getPackageProviders($app)
    {
        return [\Dievelop\LaravelPurge\ServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => __DIR__ . '/tmp/',
        ]);

        $app['config']->set('laravel-purge.disks.local', [
            'directory' => '/',
            'recursive' => false,
            'extensions' => [],
            'minutes_old' => 60,
            'delete_empty_directory' => false,
        ]);
    }

    public function setUp()
    {
        parent::setUp();

        // create & chmod temporary directory we will work from during the test
        $this->tmpDir = __DIR__ . '/tmp/';

        \File::deleteDirectory($this->tmpDir, true);
    }

    public function tearDown()
    {
        parent::tearDown();

        // clean up tmp directory
        \File::deleteDirectory($this->tmpDir, true);
    }

    /**
     * @param string $path
     */
    public function makeDir($path = '')
    {
        \File::makeDirectory($this->tmpDir . $path, 0775, true);
    }

    /**
     * @param int $expectedFiles
     * @param string $path
     */
    public function assertFileCount(int $expectedFiles, $path = '')
    {
        $dir = $this->tmpDir . $path;
        $this->assertEquals(
            $expectedFiles,
            $actualFiles = count(\File::files($dir)),
            "Expected {$expectedFiles} files but found {$actualFiles} files [{$dir}]"
        );
    }

    /**
     * @param array $expectedFiles
     * @param string $path
     */
    public function assertFiles(array $expectedFiles, $path = '')
    {
        $dir = realpath($this->tmpDir . $path);

        $actualFiles = array_map(function ($file) use ($dir) {
            return ltrim(str_replace($dir, '', $file), '/');
        }, \File::allFiles($dir, true));

        sort($expectedFiles);
        sort($actualFiles);

        $this->assertEquals(
            $expectedFiles,
            $actualFiles,
            "Expected [" . join(', ', $expectedFiles) . "] files but found [" . join(', ', $actualFiles) . "] files [{$dir}]"
        );
    }

    /**
     * @param array $expectedDirs
     * @param string $path
     */
    public function assertDirs(array $expectedDirs, $path = '')
    {
        $dir = realpath($this->tmpDir . $path);

        $actualDirs = array_map(function ($file) use ($dir) {
            return ltrim(str_replace($dir, '', $file), '/');
        }, $this->allDirectories($dir));

        sort($actualDirs);
        sort($expectedDirs);

        $this->assertEquals(
            $expectedDirs,
            $actualDirs,
            "Expected [" . join(', ', $expectedDirs) . "] directories but found [" . join(', ', $actualDirs) . "] directories [{$dir}]"
        );
    }

    private function allDirectories($path)
    {
        $dirs = \File::directories($path);
        foreach ($dirs as $dir) {
            $dirs = array_merge($dirs, $this->allDirectories($dir));
        }
        return $dirs;
    }

    /**
     * @param array|string $path
     * @param int $minutes
     */
    public function makeFile($path = '', int $minutes = 0)
    {
        if (is_array($path)) {
            foreach ($path as $file) {
                $this->makeFile($file, $minutes);
            }
        } else {
            $dir = dirname($this->tmpDir . $path);
            if (!\File::isDirectory($dir)) {
                \File::makeDirectory($dir, 0755);
            }

            \File::put($this->tmpDir . $path, 'TEST:' . now()->toDateTimeString());

            if ($minutes) {
                touch($this->tmpDir . $path, now()->timestamp + ($minutes * 60));
            }
        }
    }

}