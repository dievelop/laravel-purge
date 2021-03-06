<?php

namespace Dievelop\LaravelPurge\Commands;

use Dievelop\LaravelPurge\Services\FileCachePurgeService;
use Illuminate\Console\Command;

class LaravelPurgeCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "laravelpurge:cache 
                            {--dry-run : Show which files/directories will be removed }
                            {--debug : Print all files }
                            ";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "LaravelPurge: remove expired cache files";

    /**
     * @var FileCachePurgeService
     */
    private $service;

    /**
     * Create a new command instance.
     *
     * @param FileCachePurgeService $service
     */
    public function __construct(FileCachePurgeService $service)
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
        if ($this->confirm('Purge all expired file caches?', true)) {
            $this->line("Purging...");
            $purged = $this->service
                ->purge(function ($path, $expired) {
                    $check = $expired ? '<fg=red>✘' : '<fg=green>✔';
                    if ($this->option('debug')) {
                        $this->comment(" - {$check} " . $path);
                    } else if ($this->option('dry-run')) {
                        $this->comment("<fg=magenta>[DRY-RUN] - {$check} " . $path);
                        return false;
                    }
                });

            $this->line("Purged {$purged} cache file(s)");
            $this->line("");
        }
    }
}
