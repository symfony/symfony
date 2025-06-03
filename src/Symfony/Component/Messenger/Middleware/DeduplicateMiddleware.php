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
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class DeduplicateMiddleware implements MiddlewareInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$stamp = $envelope->last(DeduplicateStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        if (!$envelope->last(ReceivedStamp::class)) {
            $lock = $this->lockFactory->createLockFromKey($stamp->getKey(), $stamp->getTtl(), false);

            if (!$lock->acquire()) {
                return $envelope;
            }
        } elseif ($stamp->onlyDeduplicateInQueue()) {
            $this->lockFactory->createLockFromKey($stamp->getKey())->release();
        }

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } finally {
            if ($envelope->last(ReceivedStamp::class) && !$stamp->onlyDeduplicateInQueue()) {
                $this->lockFactory->createLockFromKey($stamp->getKey())->release();
            }
        }

        return $envelope;
    }
}
