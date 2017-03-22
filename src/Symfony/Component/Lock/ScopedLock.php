<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock;

/**
 * ScopedLock encapsulates a LockInterface which is automatically released when destructed.
 *
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
final class ScopedLock implements LockInterface
{
    /**
     * @var LockInterface
     */
    private $lock;

    /**
     * @param LockInterface $lock
     */
    public function __construct(LockInterface $lock)
    {
        $this->lock = $lock;
    }

    /**
     * Automatically release the underlying lock when the object is destructed.
     */
    public function __destruct()
    {
        if ($this->isAcquired()) {
            $this->release();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function acquire($blocking = false)
    {
        return $this->lock->acquire($blocking);
    }

    /**
     * {@inheritdoc}
     */
    public function refresh()
    {
        $this->lock->refresh();
    }

    /**
     * {@inheritdoc}
     */
    public function isAcquired()
    {
        return $this->lock->isAcquired();
    }

    /**
     * {@inheritdoc}
     */
    public function release()
    {
        $this->lock->release();
    }
}
