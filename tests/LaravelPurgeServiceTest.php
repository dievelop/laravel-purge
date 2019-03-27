<?php

namespace Dievelop\LaravelPurge\Tests;

use Dievelop\LaravelPurge\Services\FilePurgeService;

/**
 * Class LaravelPurgeServiceTest
 */
class LaravelPurgeServiceTest extends TestCase
{
    /**
     * @var FilePurgeService
     */
    protected $service;

    public function setUp()
    {
        parent::setUp();

        $this->service = new FilePurgeService($this->app->filesystem);
        $this->service->disk('local');
    }

    /**
     * @test
     */
    public function should_remove_old_files()
    {
        // create 3 files: 5 minutes old, 3 minutes old, and just created
        $this->makeFile('file_5.txt', -5);
        $this->makeFile('file_3.txt', -3);
        $this->makeFile('file_0.txt', 0);

        $this->assertFileCount(3);

        // purge files that are older than 1 minute
        $this->service
            ->minutesOld(1)
            ->purge();

        // we should have 1 file left
        $this->assertFiles(['file_0.txt']);
    }

    /**
     * @test
     */
    public function should_only_remove_whitelisted_extensions()
    {
        $remove = [
            'photo.jpg', '.jpg', '...jpg',
            'file.txt', 'file.TXT', 'file.TxT', '.txt.txt',
            'file.double.extension',
        ];
        $keep = [
            'file.ttxt',
            'photo.jpeg',
            'photo.extension',
        ];

        // create files
        $this->makeFile($remove, -5);
        $this->makeFile($keep, -5);

        // purge only files with specific extensions
        $this->service
            ->minutesOld(1)
            ->extensions(['.JPG', 'txt', '.double.extension'])
            ->purge();

        // we should only have the files left in the keep array
        $this->assertFiles($keep);
    }

    /**
     * @test
     */
    public function should_not_remove_blacklisted_extensions()
    {
        $this->makeFile('lower.jpg', -5);
        $this->makeFile('upper.JPG', -5);
        $this->makeFile('moving.gif', -5);
        $this->makeFile('file.jpeg', -5);

        // purge files except the ones with a jpg or gif extension
        $this->service
            ->minutesOld(1)
            ->extensionsBlacklist('jpg,GIF')
            ->purge();

        // we should only have the files left in the keep array
        $this->assertFiles(['lower.jpg', 'upper.JPG', 'moving.gif']);
    }

    /**
     * @test
     */
    public function should_clean_specific_directory()
    {
        $this->makeFile('directory_1/file.txt', -5);
        $this->makeFile('directory_1/subdir_1/file.txt', -5);
        $this->makeFile('directory_1/subdir_2/file.txt', -5);
        $this->makeFile('directory_2/file.txt', -5);
        $this->makeFile('file.txt', -5);

        // delete all files within directory_1
        $this->service
            ->minutesOld(1)
            ->directory('directory_1')
            ->purge();

        // we should still have all files except the direct file below directory_1
        $this->assertFiles([
            'directory_1/subdir_1/file.txt',
            'directory_1/subdir_2/file.txt',
            'directory_2/file.txt',
            'file.txt'
        ]);
    }

    /**
     * @test
     */
    public function should_clean_recursive_directory()
    {
        $this->makeFile('directory_1/file.txt', -5);
        $this->makeFile('directory_1/subdir_1/file.txt', -5);
        $this->makeFile('directory_1/subdir_2/file.txt', -5);
        $this->makeFile('directory_2/file.txt', -5);
        $this->makeFile('file.txt', -5);

        // delete all files within directory_1 recursively
        $this->service
            ->minutesOld(1)
            ->recursive(true)
            ->directory('directory_1')
            ->purge();

        // we should only have the files left outside of directory_1
        $this->assertFiles([
            'directory_2/file.txt',
            'file.txt'
        ]);
    }

    /**
     * @test
     */
    public function should_remove_empty_directory()
    {
        $this->makeFile('directory_1/file.txt', -5);
        $this->makeFile('directory_1/subdir_1/file.txt');
        $this->makeFile('directory_1/subdir_2/file1.txt', -5);
        $this->makeFile('directory_1/subdir_2/file2.txt', -5);
        $this->makeFile('directory_1/subdir_2/subsubdir_1/file.txt', -5);
        $this->makeFile('directory_1/subdir_2/subsubdir_2/file.txt', -5);
        $this->makeFile('directory_2/file.txt', -5);
        $this->makeFile('file.txt', -5);

        // delete all files and empty dirs within directory_1/subdir recursively
        $this->service
            ->minutesOld(1)
            ->recursive()
            ->deleteEmptyDirectory()
            ->directory('directory_1/subdir_2')
            ->purge();

        // we should only have the files left outside of directory_1
        $this->assertFiles([
            'directory_1/subdir_1/file.txt',
            'directory_1/file.txt',
            'directory_2/file.txt',
            'file.txt'
        ]);

        // all subdirs of subdir_1 should be gone
        $this->assertDirs([
            'directory_1',
            'directory_1/subdir_1',
            'directory_1/subdir_2',
            'directory_2',
        ]);
    }

    /**
     * @test
     */
    public function should_remove_from_multiple_directories()
    {
        $this->makeFile('directory_1/file.txt', -5);
        $this->makeFile('directory_2/file.txt', -5);
        $this->makeFile('directory_3/file.txt', -5);

        // delete all files and empty dirs within directory_1/subdir recursively
        $this->service
            ->minutesOld(1)
            ->directory(['directory_1', 'directory_2'])
            ->purge();

        // we should only have the files left in directory 3
        $this->assertFiles([
            'directory_3/file.txt'
        ]);
    }
}