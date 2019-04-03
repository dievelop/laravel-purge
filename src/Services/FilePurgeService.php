<?php

namespace Dievelop\LaravelPurge\Services;

use DateTime;
use DirectoryIterator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Util;
use SplFileInfo;

class FilePurgeService
{
    /**
     * @var FilesystemManager
     */
    protected $filesystem;

    /**
     * @var FilesystemAdapter
     */
    protected $disk;

    /**
     * @var array
     */
    protected $directories = [""];

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
    }

    /**
     * @param string $name
     * @return $this
     */
    public function disk(string $name)
    {
        $this->disk = $this->filesystem->disk($name);
        return $this;
    }

    /**
     * @param array|string $path
     * @return $this
     */
    public function directory($path = null)
    {
        $this->directories = Arr::wrap($path);
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
            $extensions = explode(",", $extensions);
        }

        if (is_array($extensions)) {
            $this->extensions = collect($extensions)->transform(function ($extension) {
                return "." . trim(strtolower(ltrim($extension, ".")));
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
            $extensions = explode(",", $extensions);
        }

        if (is_array($extensions)) {
            $this->extensionBlacklist = collect($extensions)->transform(function ($extension) {
                return "." . trim(strtolower(ltrim($extension, ".")));
            })->toArray();
        } else {
            $this->extensionBlacklist = null;
        }

        return $this;
    }

    /**
     * @return FilePurgeService
     * @throws \Exception
     */
    public function applyDefaultConfig()
    {
        return $this->config(Config::get("laravel-purge.defaults", []));
    }

    /**
     * @param array|string $config
     * @return $this
     */
    public function config($config)
    {
        if (is_string($config)) {
            $config = Config::get("laravel-purge.disks." . $config);
            if (is_array($config)) {
                return $this->config($config);
            } else {
                throw new \Exception("Invalid config `{$config}` ");
            }
        } elseif (is_array($config)) {

            // set config based on passed config array
            if (isset($config["disk"])) {
                $this->disk($config["disk"]);
            }
            if (isset($config["directories"])) {
                $this->directory($config["directories"]);
            }
            if (isset($config["delete_empty_directory"])) {
                $this->deleteEmptyDirectory($config["delete_empty_directory"]);
            }
            if (isset($config["recursive"])) {
                $this->recursive($config["recursive"]);
            }
            if (isset($config["extensions"])) {
                $this->extensions($config["extensions"]);
            }
            if (isset($config["extensions_blacklist"])) {
                $this->extensionsBlacklist($config["extensions_blacklist"]);
            }
            if (isset($config["minutes_old"])) {
                $this->minutesOld($config["minutes_old"]);
            }
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
     * @param DateTime $date
     * @return $this
     */
    public function olderThan(DateTime $date)
    {
        $this->time = Carbon::instance($date);
        return $this;
    }

    /**
     * @param callable|null $callback
     * @return int
     * @throws \Exception
     */
    public function purge(callable $callback = null)
    {
        if (!$this->disk) {
            throw new \Exception("No disk set to purge");
        }

        $purged = 0;

        // purge files first
        foreach ($this->directories as $directory) {
            $purged += $this->purgeDirectory($directory, $callback);
        }

        return $purged;
    }

    protected function purgeDirectory(string $directory, callable $callback = null)
    {
        $purged = 0;

        $this->contents($directory, function ($item) use (&$purged, &$directories, $callback) {
            if ($item["type"] === "file") {
                if ($this->viaCallback($item, $this->doDeleteFile($item), $callback)) {
                    if ($this->disk->delete($item["path"])) {
                        $purged++;
                    }
                }
            } elseif ($item["type"] === "dir") {
                if ($this->deleteEmptyDirectory) {
                    $directories[] = $item;
                }

                if ($this->recursive) {
                    $purged += $this->purgeDirectory($item['path'], $callback);
                }
            }
        });

        // purge empty directories
        if ($this->deleteEmptyDirectory && $directories) {
            collect($directories)->each(function ($item) use (&$purged, $callback) {
                if ($this->viaCallback($item, $this->doDeleteDirectory($item), $callback)) {
                    if ($this->disk->deleteDirectory($item["path"])) {
                        $purged++;
                    }
                }
            });
        }

        return $purged;
    }

    /**
     * @param $directory
     * @param callable|null $callback
     * @return void
     */
    protected function contents($directory, callable $callback = null)
    {
        // check if the directory path has a directory wildcard *
        $firstWildcardPosition = mb_strpos($directory, '/*');
        if ($firstWildcardPosition !== false) {

            // break the path into two chunks: everything before the first wildcard and after it
            $pathBefore = Str::substr($directory, 0, $firstWildcardPosition);
            $pathAfter = Str::substr($directory, $firstWildcardPosition + 2);

            // get all contents of everything until the first wildcard
            $this->contents($pathBefore, function ($file) use ($callback, $pathAfter) {
                // for each directory we find we continue searching
                if ($file['type'] === 'dir') {
                    $this->contents(
                        $file['path'] . $pathAfter,
                        $callback
                    );
                }
            });
        } else {
            // for local file system we user our own iterator to speed up the process a bit.
            if ($this->disk->getDriver()->getAdapter() instanceof Local) {
                $path = $this->disk->getDriver()->getAdapter()->applyPathPrefix($directory);
                $iterator = new DirectoryIterator($path);
                foreach ($iterator as $file) {
                    if ($file->isDot()) {
                        continue;
                    }

                    /** @var DirectoryIterator $file */
                    if ($callback) {
                        if (call_user_func($callback, $this->mapFileInfo($file)) === false) {
                            break;
                        }
                    }
                }
            } else {
                collect($this->disk->listContents($directory))->each($callback);
            }
        }
    }

    /**
     * Took from League\Flysystem\Adapter\Local;
     * @param SplFileInfo $file
     *
     * @return array
     */
    protected function mapFileInfo(SplFileInfo $file)
    {
        $path = $this->disk->getDriver()->getAdapter()->removePathPrefix($file->getPathname());
        trim(str_replace('\\', '/', $path), '/');

        $info = [
            'type' => $file->getType(),
            'path' => $path
        ];

        $info['timestamp'] = $file->getMTime();

        if ($info['type'] === 'file') {
            $info['size'] = $file->getSize();
        }

        return $info + Util::pathinfo($info['path']);
    }

    /**
     * @param $item
     * @param $deleting
     * @param callable|null $callback
     * @return mixed
     */
    protected function viaCallback($item, $deleting, $callback = null)
    {
        if (is_callable($callback)) {
            $response = call_user_func($callback, $item, $deleting);
            if (is_bool($response)) {
                return $response;
            }
        }
        return $deleting;
    }

    /**
     * @param $item
     * @return bool
     */
    protected function doDeleteFile($item)
    {
        // check timestamp
        if ($item["timestamp"] > $this->time->timestamp) {
            return false;
        }

        // check blacklist of extensions
        if ($this->extensionBlacklist) {
            if (Str::endsWith(strtolower($item["basename"]), $this->extensionBlacklist)) {
                return false;
            }
        }

        // check whitelist of extensions
        if ($this->extensions) {
            if (!Str::endsWith(strtolower($item["basename"]), $this->extensions)) {
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
        // check if directory is empty
        $files = $this->disk->files($item["path"]);
        if (!empty($files)) {
            return false;
        }

        return true;
    }
}