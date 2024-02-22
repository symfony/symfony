<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Manager;

use Symfony\Component\AccessToken\AccessTokenInterface;
use Symfony\Component\AccessToken\AccessTokenManagerInterface;
use Symfony\Component\AccessToken\CredentialsInterface;
use Symfony\Component\AccessToken\Exception\RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;

/**
 * Lock when fetching tokens.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class LockAccessTokenManagerDecorator implements AccessTokenManagerInterface
{
    public function __construct(
        private readonly AccessTokenManagerInterface $decorated,
        private readonly LockFactory $lockFactory,
        private int $lockTtl = 5,
    ) {}

    public function createCredentials(string $uri): CredentialsInterface
    {
        return $this->decorated->createCredentials($uri);
    }

    public function getAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        $id = $this->getLockId($credentials);
        $lock = $this->lockFactory->createLock($id, $this->lockTtl);

        $retries = 2;
        do {
            try {
                // Acquire lock in blocking mode, because someone else might be
                // rebuilding the token at the same time as us. Once it was released
                // by the other person, attempt a cache get, and go fetch the token
                // if no cache item is found.
                $lock->acquire(true);

                return $this->decorated->getAccessToken($credentials);

            } catch (LockConflictedException|LockAcquiringException) {
                // Lock failed, for any reason, attempt a cache fetch in case we'd
                // find something to send back for the user.
            } finally {
                if ($lock->isAcquired()) {
                    $lock->release();
                }
            }
        } while (--$retries);

        throw new RuntimeException('Could not acquire lock while fetching access token.');
    }

    public function refreshAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        $id = $this->getLockId($credentials);
        $lock = $this->lockFactory->createLock($id, $this->lockTtl);

        try {
            // When refreshing, do not block, there are great chances another
            // thread is actually fetching a new token. Simply wait for it to
            // finish and return with getAccessToken() instead.
            $lock->acquire(false);

            return $this->decorated->refreshAccessToken($credentials);

        } catch (LockConflictedException|LockAcquiringException) {
            // Someone else was holding the lock, attempt a fet instead
            // of a fetch. It might be any of get, refresh or delete.
            return $this->getAccessToken($credentials);

        } finally { 
            if ($lock->isAcquired()) {
                $lock->release();
            }
        }
    }

    public function deleteAccessToken(CredentialsInterface $credentials): void
    {
        $id = $this->getLockId($credentials);
        $lock = $this->lockFactory->createLock($id, $this->lockTtl);

        try {
            $lock->acquire(false);
            $this->decorated->deleteAccessToken($credentials);
        } catch (LockConflictedException|LockAcquiringException) {
            // Someone is fetching a new token, there is no need to delete it
            // in any way, simply return and live with it.
        } finally { 
            if ($lock->isAcquired()) {
                $lock->release();
            }
        }
    }

    /**
     * Compute lock resource identifier.
     */
    protected function getLockId(CredentialsInterface $credentials): string
    {
        return 'actk-' . $credentials->getId();
    }
}
