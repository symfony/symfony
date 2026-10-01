<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\SharedLockStoreInterface;

/**
 * FlockStore is a PersistingStoreInterface implementation using the FileSystem flock.
 *
 * Original implementation in \Symfony\Component\Filesystem\LockHandler.
 *
 * By default, the lock file is kept on disk when the lock is released. Keeping it suits locks
 * reused intensively under the same name, as it avoids recreating the file on each acquisition.
 * Passing true as $removeOnRelease removes the file of an exclusive lock on release, which suits
 * many distinct locks (one per database row, for example). The store then checks that the file
 * it locked is still linked, and retries on the new file otherwise. Shared locks keep their file.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Romain Neutron <imprec@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FlockStore implements BlockingStoreInterface, SharedLockStoreInterface
{
    private readonly string $lockPath;

    /**
     * @param string|null $lockPath        the directory to store the lock, defaults to the system's temporary directory
     * @param bool        $removeOnRelease whether the lock file is removed when the lock is released;
     *                                     only exclusive locks are removed, shared locks are kept
     *
     * @throws LockStorageException If the lock directory doesn’t exist or is not writable
     */
    public function __construct(?string $lockPath = null, private readonly bool $removeOnRelease = false)
    {
        if (!is_dir($lockPath ??= sys_get_temp_dir())) {
            if (!@mkdir($lockPath, 0o777, true) && !is_dir($lockPath)) {
                throw new InvalidArgumentException(\sprintf('The FlockStore directory "%s" does not exist and cannot be created.', $lockPath));
            }
        } elseif (!is_writable($lockPath)) {
            throw new InvalidArgumentException(\sprintf('The FlockStore directory "%s" is not writable.', $lockPath));
        }

        $this->lockPath = $lockPath;
    }

    public function save(Key $key): void
    {
        $this->lock($key, false, false);
    }

    public function saveRead(Key $key): void
    {
        $this->lock($key, true, false);
    }

    public function waitAndSave(Key $key): void
    {
        $this->lock($key, false, true);
    }

    public function waitAndSaveRead(Key $key): void
    {
        $this->lock($key, true, true);
    }

    private function lock(Key $key, bool $read, bool $blocking): void
    {
        $handle = null;
        // The lock is maybe already acquired.
        if ($key->hasState(__CLASS__)) {
            [$stateRead, $handle] = $key->getState(__CLASS__);
            // Check for promotion or demotion
            if ($stateRead === $read) {
                return;
            }
        }

        $fileName = \sprintf('%s/sf.%s.%s.lock',
            $this->lockPath,
            substr(preg_replace('/[^a-z0-9\._-]+/i', '-', $key), 0, 50),
            strtr(substr(base64_encode(hash('sha256', $key, true)), 0, 7), '/', '_')
        );

        while (true) {
            if (!$handle) {
                $handle = $this->openFile($fileName);
            }

            // On Windows, even if PHP doc says the contrary, LOCK_NB works, see
            // https://bugs.php.net/54129
            if (!flock($handle, ($read ? \LOCK_SH : \LOCK_EX) | ($blocking ? 0 : \LOCK_NB))) {
                fclose($handle);
                throw new LockConflictedException();
            }

            if (!$this->removeOnRelease) {
                break;
            }

            // When the lock file is removed on release, the file may have been removed and
            // recreated by another process while this one was waiting for the lock. The handle
            // would then point to the removed file, which is not the one the path resolves to
            // anymore, and two processes could hold the lock at the same time. A removed file
            // has a link count of zero, so release the handle and retry on the new file.
            if (false === $stat = fstat($handle)) {
                flock($handle, \LOCK_UN);
                fclose($handle);
                throw new LockStorageException('Unable to read the status of the lock file.');
            }

            if (0 < $stat['nlink']) {
                break;
            }

            flock($handle, \LOCK_UN);
            fclose($handle);
            $handle = null;

            // A non-blocking acquire does not wait, so report the contention instead of retrying.
            if (!$blocking) {
                throw new LockConflictedException();
            }
        }

        $key->setState(__CLASS__, [$read, $handle, $fileName]);
        $key->markUnserializable();
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        // do nothing, the flock locks forever.
    }

    public function delete(Key $key): void
    {
        // The lock is maybe not acquired.
        if (!$key->hasState(__CLASS__)) {
            return;
        }

        [$read, $handle, $fileName] = $key->getState(__CLASS__);

        // Remove the file while the lock is still held, so that a process waiting for the lock
        // detects the removed file once it acquires it and retries on a new one. Only exclusive
        // locks are removed: a shared lock may be held by other processes that would keep
        // holding the removed file while a new one gets created.
        if ($this->removeOnRelease && !$read) {
            @unlink($fileName);
        }

        flock($handle, \LOCK_UN | \LOCK_NB);
        fclose($handle);

        $key->removeState(__CLASS__);
    }

    public function exists(Key $key): bool
    {
        return $key->hasState(__CLASS__);
    }

    /**
     * @return resource
     */
    private function openFile(string $fileName)
    {
        $error = 'Unable to open the lock file.';

        // Silence error reporting
        set_error_handler(static function ($type, $msg) use (&$error): bool {
            $error = $msg;

            return true;
        });
        try {
            if (!$handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r')) {
                if ($handle = fopen($fileName, 'x')) {
                    chmod($fileName, 0o666);
                } elseif (!$handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r')) {
                    usleep(100); // Give some time for chmod() to complete
                    $handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r');
                }
            }
        } finally {
            restore_error_handler();
        }

        if (!$handle) {
            throw new LockStorageException($error, 0, null);
        }

        return $handle;
    }
}
