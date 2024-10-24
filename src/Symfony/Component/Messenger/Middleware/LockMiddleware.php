<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\LockStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class LockMiddleware implements MiddlewareInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$stamp = $envelope->last(LockStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        if (!$envelope->last(ReceivedStamp::class)) {
            if ($stamp->shouldDiscardDuplicate()) {
                $lock = $this->lockFactory->createLockFromKey($stamp->getKey(), $stamp->getTtl(), autoRelease: false);
                if (!$lock->acquire()) {
                    return $envelope;
                }
            }
        } elseif ($stamp->shouldBlockDuplicateInProcess()) {
            $lock = $this->lockFactory->createLockFromKey($stamp->getKey(), $stamp->getTtl(), autoRelease: false);
            $lock->acquire(true);
        } elseif ($stamp->shouldBeReleasedBeforeHandlerCall()) {
            $this->lockFactory->createLockFromKey($stamp->getKey())->release();
        }

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } finally {
            if ($envelope->last(ReceivedStamp::class) && !$stamp->shouldBeReleasedBeforeHandlerCall()) {
                $this->lockFactory->createLockFromKey($stamp->getKey())->release();
            }
        }

        return $envelope;
    }
}
