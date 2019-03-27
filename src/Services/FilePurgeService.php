<?php

namespace Dievelop\LaravelPurge\Services;

use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FilePurgeService
{
    /**
     * @var FilesystemManager
     */
    protected $filesystem;

    /**
     * @var \Illuminate\Filesystem\FilesystemAdapter
     */
    protected $disk;

    /**
     * @var array
     */
    protected $directories = [''];

    /**
     * @var boolean
     */
    protected $deleteEmptyDirectory = false;

    /**
     * @var string
     */
    protected $recursive = false;

    /**
     * @var array
     */
    protected $extensions;

    /**
     * @var array
     */
    protected $extensionBlacklist;

    /**
     * @var Carbon
     */
    protected $time;

    /**
     * FilePurgeService constructor.
     * @param FilesystemManager $filesystem
     */
    public function __construct(FilesystemManager $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->time = now();
        $this->extensionsBlacklist(config('laravel-purge.extensions_blacklist', []));
    }

    /**
     * @param string $name
     * @param bool $autoloadConfig
     * @return $this
     */
    public function disk(string $name, $autoloadConfig = true)
    {
        $this->disk = $this->filesystem->disk($name);
        if ($autoloadConfig === true) {
            $this->fromConfig(config('laravel-purge.disks.' . $name, []));
        }
        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function directory($path = null)
    {
        $this->directories = array_wrap($path);
        return $this;
    }

    /**
     * @param boolean $boolean
     * @return $this
     */
    public function deleteEmptyDirectory($boolean = true)
    {
        $this->deleteEmptyDirectory = (bool)$boolean;
        return $this;
    }

    /**
     * @param boolean $boolean
     * @return $this
     */
    public function recursive($boolean = true)
    {
        $this->recursive = (bool)$boolean;
        return $this;
    }

    /**
     * @param array|string $extensions
     * @return $this
     */
    public function extensions($extensions)
    {
        if (is_string($extensions)) {
            $extensions = explode(',', $extensions);
        }

        if (is_array($extensions)) {
            $this->extensions = collect($extensions)->transform(function ($extension) {
                return '.' . trim(strtolower(ltrim($extension, '.')));
            })->toArray();
        } else {
            $this->extensions = null;
        }

        return $this;
    }

    /**
     * @param array|string $extensions
     * @return $this
     */
    public function extensionsBlacklist($extensions)
    {
        if (is_string($extensions)) {
            $extensions = explode(',', $extensions);
        }

        if (is_array($extensions)) {
            $this->extensionBlacklist = collect($extensions)->transform(function ($extension) {
                return '.' . trim(strtolower(ltrim($extension, '.')));
            })->toArray();
        } else {
            $this->extensionBlacklist = null;
        }

        return $this;
    }

    /**
     * @param $config
     * @return $this
     */
    public function fromConfig(array $config)
    {
        if (is_array($config)) {
            $this->directory($config['directory'] ?? null);
            $this->deleteEmptyDirectory($config['delete_empty_directory'] ?? false);
            $this->recursive($config['recursive'] ?? false);
            $this->extensions($config['extensions'] ?? null);
            $this->minutesOld($config['minutes_old'] ?? 43200); // 30 days = fallback
        }
        return $this;
    }

    /**
     * @param $minutes
     * @return $this
     */
    public function minutesOld($minutes)
    {
        $this->time = now()->subMinutes($minutes);
        return $this;
    }

    /**
     * @param \DateTime $date
     * @return $this
     */
    public function olderThan(\DateTime $date)
    {
        $this->time = Carbon::instance($date);
        return $this;
    }

    public function purge()
    {
        if (!$this->disk) {
            throw new \Exception('No disk set to purge');
        }

        // purge files first
        foreach($this->directories as $directory) {
            collect($this->disk->listContents($directory, $this->recursive))->each(function ($item) {
                if ($this->doDeleteFile($item)) {
                    $this->disk->delete($item['path']);
                }
            });

            // purge empty directories
            if ($this->deleteEmptyDirectory) {
                collect($this->disk->listContents($directory, $this->recursive))->each(function ($item) {
                    if ($this->doDeleteDirectory($item)) {
                        $this->disk->deleteDirectory($item['path']);
                    }
                });
            }
        }
    }

    /**
     * @param $item
     * @return bool
     */
    protected function doDeleteFile($item)
    {
        // check type
        if ($item['type'] !== 'file') {
            return false;
        }

        // check timestamp
        if ($item['timestamp'] > $this->time->timestamp) {
            return false;
        }

        // check blacklist of extensions
        if ($this->extensionBlacklist) {
            if (Str::endsWith(strtolower($item['basename']), $this->extensionBlacklist)) {
                return false;
            }
        }

        // check whitelist of extensions
        if ($this->extensions) {
            if (!Str::endsWith(strtolower($item['basename']), $this->extensions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $item
     * @return bool
     */
    protected function doDeleteDirectory($item)
    {
        // check type
        if ($item['type'] !== 'dir') {
            return false;
        }

        // check if directory is empty
        $files = $this->disk->files($item['path']);
        if (!empty($files)) {
            return false;
        }

        return true;
    }
}