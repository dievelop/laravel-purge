<?php

namespace Dievelop\LaravelPurge\Commands;

use Dievelop\LaravelPurge\Services\FilePurgeService;
use Illuminate\Console\Command;

class LaravelPurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purge:disks {--disk=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'LaravelPurge: remove files older than given time';

    /**
     * @var FilePurgeService
     */
    private $service;

    /**
     * Create a new command instance.
     *
     * @param FilePurgeService $service
     */
    public function __construct(FilePurgeService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $disks = $this->getDisks();
        if ($disks) {
            foreach($disks as $disk) {
                if ($this->confirm('Purge disk: '. $disk .'?', true)) {
                    $this->service
                        ->disk($disk)
                        ->purge();
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getDisks()
    {
        $disk = $this->option('disk');
        if ($disk) {
            // single disk
            if (is_array(config('laravel-purge.disks.' . $disk))) {
                return [$disk];
            }
            $this->error('Disk ' . $disk . ' not configured in configuration [laravel-purge.disks]');
            return null;
        } else {
            // all configured disks
            return array_keys(config('laravel-purge'));
        }
    }
}
