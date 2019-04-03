<?php

namespace Dievelop\LaravelPurge\Services;

use FilesystemIterator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileCachePurgeService
{
    /**
     * @param callable|null $callback
     * @return int
     * @throws \Exception
     */
    public function purge(callable $callback = null)
    {
        $purged = 0;
        $directory = Config::get('cache.stores.file.path');
        if (!$directory) {
            throw new \Exception("No directory set in `cache.stores.file.path`");
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) {
                continue;
            }

            if ($timestamp = $this->getExpirationTime($file->getPathname())) {
                if ($this->viaCallback($file->getPathname(), $this->isExpired($timestamp), $callback)) {
                    if ($this->deleteCacheFile($file->getPathname())) {
                        $purged++;
                    }
                }
            }
        }

        return $purged;
    }

    /**
     * @param $path
     * @param $expired
     * @param callable|null $callback
     * @return mixed
     */
    protected function viaCallback($path, $expired, $callback = null)
    {
        if (is_callable($callback)) {
            $response = call_user_func($callback, $path, $expired);
            if (is_bool($response)) {
                return $response;
            }
        }
        return $expired;
    }

    /**
     * @param $path
     * @return null|int
     */
    protected function getExpirationTime($path)
    {
        // example cache content: {unix_timestamp}{cache_content} => eg: 1554148303s:5:"value";
        $timestamp = file_get_contents($path, false, null, 0, 10);
        if (is_numeric($timestamp)) {
            return $timestamp;
        }
        return null;
    }

    /**
     * @param $timestamp
     * @return boolean
     */
    protected function isExpired($timestamp)
    {
        return Carbon::now()->timestamp >= $timestamp;
    }

    /**
     * @param $timestamp
     * @return boolean
     */
    protected function deleteCacheFile($path)
    {
        return @unlink($path);
    }
}