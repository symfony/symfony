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
 * By default, the lock file is kept on disk when the lock is released, and the next acquisition of the same name reuses it.
 * Passing true as $removeOnRelease removes the file of an exclusive lock on release, which suits many distinct locks (one per database row, for example).
 * The store then checks that the file it locked is still the one at its path, and retries on the new file otherwise.
 * This is slower for a name that is locked again and again: the file is created on each acquisition, and the processes waiting on a removed file have to retry.
 * Shared locks keep their file.
 * All processes sharing a lock directory must use the same setting.
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
     * @param string|null $lockPath        The directory to store the lock, defaults to the system's temporary directory
     * @param bool        $removeOnRelease Whether releasing an exclusive lock removes its file, shared locks keep theirs
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

            // Another process may have removed the file, and a new one may have been created at its path, while this one was waiting for the lock.
            // The handle would then point to a file the path does not resolve to anymore, and two processes could hold the lock at the same time.
            // Comparing device and inode numbers detects it, also on NFS where a removed file that is still open survives under a .nfsXXXX name.
            if (false === $stat = fstat($handle)) {
                flock($handle, \LOCK_UN);
                fclose($handle);
                throw new LockStorageException('Unable to read the status of the lock file.');
            }

            clearstatcache(true, $fileName);

            if (($pathStat = @stat($fileName)) && $stat['dev'] === $pathStat['dev'] && $stat['ino'] === $pathStat['ino']) {
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

        // Removing the file while the lock is still held lets a process waiting for the lock detect the removal once it acquires it.
        // A shared lock keeps its file: other processes may hold it too, and would keep holding the removed file while a new one gets created.
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
            // When files are removed on release, other processes may remove and create the file between the attempts below.
            // On Windows, a removed file also stays in the way until all the processes that opened it close it.
            for ($retry = 0;; ++$retry) {
                if (!$handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r')) {
                    if ($handle = fopen($fileName, 'x')) {
                        chmod($fileName, 0o666);
                    } elseif (!$handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r')) {
                        usleep(100); // Give some time for chmod() to complete
                        $handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r');
                    }
                }

                if ($handle || !$this->removeOnRelease || 10 <= $retry) {
                    break;
                }

                usleep(min(1000 << $retry, 100000));
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
