<?php

namespace Dievelop\LaravelPurge\Tests;

use Dievelop\LaravelPurge\Services\FileCachePurgeService;
use Illuminate\Support\Facades\Cache;

/**
 * Class LaravelFileCachePurgeServiceTest
 * @covers \Dievelop\LaravelPurge\Services\FileCachePurgeService
 */
class LaravelFileCachePurgeServiceTest extends TestCase
{
    /**
     * @var FileCachePurgeService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();

        // set up the file purg service
        $this->service = new FileCachePurgeService();
    }

    /**
     * @test
     */
    public function should_keep_unexpired_cache_untouched()
    {
        Cache::put('test', 'value', now()->addSeconds(5));
        $purged = $this->service->purge();
        $this->assertEquals(0, $purged);
    }

    /**
     * @test
     */
    public function should_remove_expired_cache()
    {
        Cache::put('test', 'value', now()->addSeconds(5));
        Cache::put('test_expired', 'value', now()->addSeconds(1));
        sleep(2);
        $purged = $this->service->purge();
        $this->assertEquals(1, $purged);
    }

    /**
     * @test
     */
    public function should_respect_callback_response()
    {
        Cache::put('test', 'value', now()->addSeconds(5));
        Cache::put('test_expired', 'value', now()->addSeconds(1));
        sleep(2);
        $purged = $this->service->purge(function($path, $expired) {
            return false;
        });
        $this->assertEquals(0, $purged);

        $purged = $this->service->purge(function($path, $expired) {
            return true;
        });
        $this->assertEquals(2, $purged);
    }
}