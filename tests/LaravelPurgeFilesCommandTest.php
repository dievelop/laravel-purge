<?php

namespace Dievelop\LaravelPurge\Tests;

/**
 * Class LaravelPurgeFilesCommandTest
 * @covers \Dievelop\LaravelPurge\Commands\LaravelPurgeFilesCommand
 */
class LaravelPurgeFilesCommandTest extends TestCase
{
    /**
     * @test
     */
    public function should_purge_all_disks_in_configuration()
    {
        $this->artisan('laravelpurge:files')
            ->expectsQuestion('Purge config: `config_1`?', true)
            ->expectsOutput('Purging...')
            ->expectsOutput('Purged 0 files and/or directories')
            ->expectsQuestion('Purge config: `config_2`?', true)
            ->expectsOutput('Purging...')
            ->expectsOutput('Purged 0 files and/or directories')
            ->assertExitCode(0);
    }

    /**
     * @test
     */
    public function should_purge_single_disk()
    {
        $this->artisan('laravelpurge:files', ['--config' => 'config_2'])
            ->expectsQuestion('Purge config: `config_2`?', true)
            ->expectsOutput('Purging...')
            ->expectsOutput('Purged 0 files and/or directories')
            ->assertExitCode(0);
    }

    /**
     * @test
     */
    public function should_stop_with_invalid_disk()
    {
        $this->artisan('laravelpurge:files', ['--config' => 'unknown_config'])
            ->expectsOutput('Config key `unknown_config` is not configured in configuration [laravel-purge.disks]')
            ->assertExitCode(0);
    }

    /**
     * @test
     */
    public function should_purge_files()
    {
        $this->tmpDir = $this->rootDir . '/disk_1/';
        $this->makeFile('file_62.txt', -62);
        $this->makeFile('file_61.txt', -61);
        $this->makeFile('file_59.txt', -59);
        $this->makeFile('file_0.txt', 0);

        $this->artisan('laravelpurge:files', ['--config' => 'config_1'])
            ->expectsQuestion('Purge config: `config_1`?', true)
            ->expectsOutput('Purging...')
            ->expectsOutput('Purged 2 files and/or directories')
            ->assertExitCode(0);

        $this->assertfiles(['file_0.txt', 'file_59.txt']);
    }

    /**
     * @test
     */
    public function should_run_with_dry_run()
    {
        $this->tmpDir = $this->rootDir . 'disk_1/';
        $this->makeFile('file_62.txt', -62);
        $this->makeFile('file_61.txt', -61);
        $this->makeFile('file_59.txt', -59);
        $this->makeFile('file_0.txt', 0);

        $this->artisan('laravelpurge:files', [
            '--config' => 'config_1',
            '--dry-run' => true,
        ])
            ->expectsQuestion('Purge config: `config_1`?', true)
            ->expectsOutput('Purging...')
            ->expectsOutput(' - [DRY-RUN] ✘ file_61.txt')
            ->expectsOutput(' - [DRY-RUN] ✘ file_62.txt')
            ->expectsOutput('Purged 0 files and/or directories')
            ->assertExitCode(0);

        $this->assertfiles(['file_0.txt', 'file_59.txt', 'file_61.txt', 'file_62.txt']);

        $this->artisan('laravelpurge:files', [
            '--config' => 'config_1',
            '--dry-run' => true,
            '--debug' => true,
        ])
            ->expectsQuestion('Purge config: `config_1`?', true)
            ->expectsOutput('Purging...')
            ->expectsOutput(' - [DRY-RUN] ✔ file_0.txt')
            ->expectsOutput(' - [DRY-RUN] ✔ file_59.txt')
            ->expectsOutput(' - [DRY-RUN] ✘ file_61.txt')
            ->expectsOutput(' - [DRY-RUN] ✘ file_62.txt')
            ->expectsOutput('Purged 0 files and/or directories')
            ->assertExitCode(0);
    }
}