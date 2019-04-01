<?php

namespace Dievelop\LaravelPurge\Tests;

use Illuminate\Support\Facades\Cache;

/**
 * Class LaravelPurgeFileCacheCommandTest
 * @covers \Dievelop\LaravelPurge\Commands\LaravelPurgeFilesCommand
 */
class LaravelPurgeFileCacheCommandTest extends TestCase
{
    /**
     * @test
     */
    public function should_run_purge_cache_files_command()
    {
        $this->artisan('laravelpurge:cache')
            ->expectsQuestion('Purge all expired file caches?', true)
            ->expectsOutput('Purging...')
            ->expectsOutput('Purged 0 cache file(s)')
            ->assertExitCode(0);
    }

    /**
     * @test
     */
    public function should_purge_expired_cache_file()
    {
        Cache::put('test', 'value', now()->addSeconds(5));
        Cache::put('test_expired', 'value', now()->addSeconds(1));
        sleep(2);

        $this->artisan('laravelpurge:cache')
            ->expectsQuestion('Purge all expired file caches?', true)
            ->expectsOutput('Purging...')
            ->expectsOutput('Purged 1 cache file(s)')
            ->assertExitCode(0);
    }
}