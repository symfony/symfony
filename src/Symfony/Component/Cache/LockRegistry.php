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

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * LockRegistry is used internally by existing adapters to protect against cache stampede.
 *
 * It does so by wrapping the computation of items in a pool of locks.
 * Foreach each apps, there can be at most 20 concurrent processes that
 * compute items at the same time and only one per cache-key.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class LockRegistry
{
    private static $openedFiles = [];
    private static $lockedFiles;
    private static $signalingException;
    private static $signalingCallback;

    /**
     * The number of items in this list controls the max number of concurrent processes.
     */
    private static $files = [
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'AbstractAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'AbstractTagAwareAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'AdapterInterface.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ApcuAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ArrayAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ChainAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'CouchbaseBucketAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'CouchbaseCollectionAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'DoctrineDbalAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'FilesystemAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'FilesystemTagAwareAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'MemcachedAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'NullAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ParameterNormalizer.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'PdoAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'PhpArrayAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'PhpFilesAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'ProxyAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'Psr16Adapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'RedisAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'RedisTagAwareAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'TagAwareAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'TagAwareAdapterInterface.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'TraceableAdapter.php',
        __DIR__.\DIRECTORY_SEPARATOR.'Adapter'.\DIRECTORY_SEPARATOR.'TraceableTagAwareAdapter.php',
    ];

    /**
     * Defines a set of existing files that will be used as keys to acquire locks.
     *
     * @return array The previously defined set of files
     */
    public static function setFiles(array $files): array
    {
        $previousFiles = self::$files;
        self::$files = $files;

        foreach (self::$openedFiles as $file) {
            if ($file) {
                flock($file, \LOCK_UN);
                fclose($file);
            }
        }
        self::$openedFiles = self::$lockedFiles = [];

        return $previousFiles;
    }

    public static function compute(callable $callback, ItemInterface $item, bool &$save, CacheInterface $pool, \Closure $setMetadata = null, LoggerInterface $logger = null)
    {
        if ('\\' === \DIRECTORY_SEPARATOR && null === self::$lockedFiles) {
            // disable locking on Windows by default
            self::$files = self::$lockedFiles = [];
        }

        $key = self::$files ? abs(crc32($item->getKey())) % \count(self::$files) : -1;

        if ($key < 0 || self::$lockedFiles || !$lock = self::open($key)) {
            return $callback($item, $save);
        }

        self::$signalingException ??= unserialize("O:9:\"Exception\":1:{s:16:\"\0Exception\0trace\";a:0:{}}");
        self::$signalingCallback ??= function () { throw self::$signalingException; };

        while (true) {
            try {
                $locked = false;
                // race to get the lock in non-blocking mode
                $locked = flock($lock, \LOCK_EX | \LOCK_NB, $wouldBlock);

                if ($locked || !$wouldBlock) {
                    $logger?->info(sprintf('Lock %s, now computing item "{key}"', $locked ? 'acquired' : 'not supported'), ['key' => $item->getKey()]);
                    self::$lockedFiles[$key] = true;

                    $value = $callback($item, $save);

                    if ($save) {
                        if ($setMetadata) {
                            $setMetadata($item);
                        }

                        $pool->save($item->set($value));
                        $save = false;
                    }

                    return $value;
                }
                // if we failed the race, retry locking in blocking mode to wait for the winner
                $logger?->info('Item "{key}" is locked, waiting for it to be released', ['key' => $item->getKey()]);
                flock($lock, \LOCK_SH);
            } finally {
                flock($lock, \LOCK_UN);
                unset(self::$lockedFiles[$key]);
            }

            try {
                $value = $pool->get($item->getKey(), self::$signalingCallback, 0);
                $logger?->info('Item "{key}" retrieved after lock was released', ['key' => $item->getKey()]);
                $save = false;

                return $value;
            } catch (\Exception $e) {
                if (self::$signalingException !== $e) {
                    throw $e;
                }
                $logger?->info('Item "{key}" not found while lock was released, now retrying', ['key' => $item->getKey()]);
            }
        }

        return null;
    }

    private static function open(int $key)
    {
        if (null !== $h = self::$openedFiles[$key] ?? null) {
            return $h;
        }
        set_error_handler(function () {});
        try {
            $h = fopen(self::$files[$key], 'r+');
        } finally {
            restore_error_handler();
        }

        return self::$openedFiles[$key] = $h ?: @fopen(self::$files[$key], 'r');
    }
}
