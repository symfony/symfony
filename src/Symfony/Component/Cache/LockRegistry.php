<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * LockRegistry is used internally by existing adapters to protect against cache stampede.
 *
 * It does so by wrapping the computation of items in a pool of locks.
 * Foreach each apps, there can be at most 20 concurrent processes that
 * compute items at the same time and only one per cache-key.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LockRegistry
{
    private static $save;
    private static $openedFiles = array();
    private static $lockedFiles = array();

    /**
     * The number of items in this list controls the max number of concurrent processes.
     */
    private static $files = array(
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'AbstractAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'AdapterInterface.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ApcuAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ArrayAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ChainAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'DoctrineAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'FilesystemAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'MemcachedAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'NullAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'PdoAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'PhpArrayAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'PhpFilesAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ProxyAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'RedisAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'SimpleCacheAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'TagAwareAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'TagAwareAdapterInterface.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'TraceableAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'TraceableTagAwareAdapter.php',
    );

    /**
     * Defines a set of existing files that will be used as keys to acquire locks.
     *
     * @return array The previously defined set of files
     */
    public static function setFiles(array $files): array
    {
        $previousFiles = self::$files;
        self::$files = $files;

        foreach (self::$openedFiles as $k => $file) {
            flock($file, LOCK_UN);
            fclose($file);
        }
        self::$openedFiles = self::$lockedFiles = array();

        return $previousFiles;
    }

    /**
     * @internal
     */
    public static function save(string $key, CacheItemPoolInterface $pool, CacheItemInterface $item, callable $callback, float $startTime, &$value): bool
    {
        self::$save = self::$save ?? \Closure::bind(
            function (CacheItemPoolInterface $pool, CacheItemInterface $item, $value, float $startTime) {
                if ($item instanceof CacheItem && $startTime && $item->expiry > $endTime = microtime(true)) {
                    $item->newMetadata[CacheItem::METADATA_EXPIRY] = $item->expiry;
                    $item->newMetadata[CacheItem::METADATA_CTIME] = 1000 * (int) ($endTime - $startTime);
                }
                $pool->save($item->set($value));

                return $value;
            },
            null,
            CacheItem::class
        );

        $key = self::$files ? crc32($key) % \count(self::$files) : -1;

        if ($key < 0 || (self::$lockedFiles[$key] ?? false) || !$lock = self::open($key)) {
            $value = (self::$save)($pool, $item, $callback($item), $startTime);

            return true;
        }

        try {
            // race to get the lock in non-blocking mode
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                self::$lockedFiles[$key] = true;
                $value = (self::$save)($pool, $item, $callback($item), $startTime);

                return true;
            }
            // if we failed the race, retry locking in blocking mode to wait for the winner
            flock($lock, LOCK_SH);
        } finally {
            flock($lock, LOCK_UN);
            self::$lockedFiles[$key] = false;
        }

        return false;
    }

    private static function open(int $key)
    {
        if ($h = self::$openedFiles[$key] ?? null) {
            return $h;
        }
        if ($h = fopen(self::$files[$key], 'rb')) {
            return self::$openedFiles[$key] = $h;
        }
    }
}
