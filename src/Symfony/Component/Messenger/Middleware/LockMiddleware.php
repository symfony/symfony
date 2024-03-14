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

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Message\LockableMessageInterface;
use Symfony\Component\Messenger\Message\TTLAwareLockableMessageInterface;
use Symfony\Component\Messenger\Stamp\LockStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class LockMiddleware implements MiddlewareInterface
{
    private LockFactory $lockFactory;

    public function __construct(LockFactory $lockFactory)
    {
        $this->lockFactory = $lockFactory;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        if (!$message instanceof LockableMessageInterface) {
            return $stack->next()->handle($envelope, $stack);
        }

        if (null === $envelope->last(ReceivedStamp::class)) {
            // If we're trying to dispatch a lockable message.
            $keyResource = $message->getKey();

            if (null !== $keyResource) {
                $key = new Key($keyResource);

                // The acquire call must be done before stamping the message
                // in order to have the full state of the key in the stamp.
                $lock = $message instanceof TTLAwareLockableMessageInterface
                    ? $this->lockFactory->createLockFromKey($key, $message->getTTL(), autoRelease: false)
                    : $this->lockFactory->createLockFromKey($key, autoRelease: false);

                if (!$lock->acquire()) {
                    return $envelope;
                }

                // The acquire call must be done before stamping the message
                // in order to have the full state of the key in the stamp.
                $envelope = $envelope->with(new LockStamp($key, $message->shouldBeReleasedBeforeHandlerCall()));
            }
        } else {
            $this->releaseLock($envelope, true);
        }

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } finally {
            // If we've received a lockable message, we're releasing it.
            if (null !== $envelope->last(ReceivedStamp::class)) {
                $this->releaseLock($envelope, false);
            }
        }

        return $envelope;
    }

    private function releaseLock(Envelope $envelope, bool $beforeHandlerCall): void
    {
        $stamp = $envelope->last(LockStamp::class);
        if ($stamp instanceof LockStamp && $stamp->shouldBeReleasedBeforeHandlerCall() === $beforeHandlerCall) {
            $this->lockFactory->createLockFromKey($stamp->getKey())->release();
        }
    }
}
