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
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Romain Neutron <imprec@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FlockStore implements BlockingStoreInterface, SharedLockStoreInterface
{
    private ?string $lockPath;

    /**
     * @param string|null $lockPath the directory to store the lock, defaults to the system's temporary directory
     *
     * @throws LockStorageException If the lock directory doesn’t exist or is not writable
     */
    public function __construct(?string $lockPath = null)
    {
        if (!is_dir($lockPath ??= sys_get_temp_dir())) {
            if (false === @mkdir($lockPath, 0777, true) && !is_dir($lockPath)) {
                throw new InvalidArgumentException(sprintf('The FlockStore directory "%s" does not exists and cannot be created.', $lockPath));
            }
        } elseif (!is_writable($lockPath)) {
            throw new InvalidArgumentException(sprintf('The FlockStore directory "%s" is not writable.', $lockPath));
        }

        $this->lockPath = $lockPath;
    }

    /**
     * @return void
     */
    public function save(Key $key)
    {
        $this->lock($key, false, false);
    }

    /**
     * @return void
     */
    public function saveRead(Key $key)
    {
        $this->lock($key, true, false);
    }

    /**
     * @return void
     */
    public function waitAndSave(Key $key)
    {
        $this->lock($key, false, true);
    }

    /**
     * @return void
     */
    public function waitAndSaveRead(Key $key)
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

        if (!$handle) {
            $fileName = sprintf('%s/sf.%s.%s.lock',
                $this->lockPath,
                substr(preg_replace('/[^a-z0-9\._-]+/i', '-', $key), 0, 50),
                strtr(substr(base64_encode(hash('sha256', $key, true)), 0, 7), '/', '_')
            );

            // Silence error reporting
            set_error_handler(function ($type, $msg) use (&$error) { $error = $msg; });
            try {
                if (!$handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r')) {
                    if ($handle = fopen($fileName, 'x')) {
                        chmod($fileName, 0666);
                    } elseif (!$handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r')) {
                        usleep(100); // Give some time for chmod() to complete
                        $handle = fopen($fileName, 'r+') ?: fopen($fileName, 'r');
                    }
                }
            } finally {
                restore_error_handler();
            }
        }

        if (!$handle) {
            throw new LockStorageException($error, 0, null);
        }

        // On Windows, even if PHP doc says the contrary, LOCK_NB works, see
        // https://bugs.php.net/54129
        if (!flock($handle, ($read ? \LOCK_SH : \LOCK_EX) | ($blocking ? 0 : \LOCK_NB))) {
            fclose($handle);
            throw new LockConflictedException();
        }

        $key->setState(__CLASS__, [$read, $handle]);
        $key->markUnserializable();
    }

    /**
     * @return void
     */
    public function putOffExpiration(Key $key, float $ttl)
    {
        // do nothing, the flock locks forever.
    }

    /**
     * @return void
     */
    public function delete(Key $key)
    {
        // The lock is maybe not acquired.
        if (!$key->hasState(__CLASS__)) {
            return;
        }

        $handle = $key->getState(__CLASS__)[1];

        flock($handle, \LOCK_UN | \LOCK_NB);
        fclose($handle);

        $key->removeState(__CLASS__);
    }

    public function exists(Key $key): bool
    {
        return $key->hasState(__CLASS__);
    }
}
