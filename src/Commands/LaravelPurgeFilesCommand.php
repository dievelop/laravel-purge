<?php

namespace Dievelop\LaravelPurge\Commands;

use Dievelop\LaravelPurge\Services\FilePurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

class LaravelPurgeFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "laravelpurge:files 
                            {--config= : Config key defined in laravel-purge.disks.KEY }
                            {--dry-run : Show which files/directories will be removed }
                            {--debug : Print all files }
                            ";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "LaravelPurge: remove files older than given time";

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
        $configKeys = $this->getConfigKeys();
        if ($configKeys) {
            foreach ($configKeys as $configKey) {
                if ($this->confirm("Purge config: `{$configKey}`?", true)) {
                    $this->line("Purging...");

                    $purged = $this->service
                        ->applyDefaultConfig()
                        ->config($configKey)
                        ->purge(function ($item, $deleting) {
                            if ($this->option('debug')) {
                                $prefix = $this->option('dry-run') ? '<fg=magenta>[DRY-RUN] ' : '';
                                $check = $deleting ? '<fg=red>✘' : '<fg=green>✔';
                                $this->comment(" - {$prefix}{$check} " . $item['path']);
                            }

                            if ($this->option('dry-run')) {
                                return false;
                            }
                        });

                    $this->line("Purged {$purged} files and/or directories");
                    $this->line("");
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getConfigKeys()
    {
        $config = $this->option("config");
        if ($config) {
            $config = Arr::wrap($config);

            // check if config key is configured
            foreach ($config as $key) {
                if (!is_array(Config::get("laravel-purge.disks." . $key))) {
                    $this->error("Config key `{$key}` is not configured in configuration [laravel-purge.disks]");
                    return null;
                }
            }

            return $config;
        } else {
            // all config
            return array_keys(Config::get("laravel-purge.disks"));
        }
    }
}
