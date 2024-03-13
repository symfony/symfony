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
    private ?LockFactory $lockFactory = null;

    public function __construct(
        ?LockFactory $lockFactory,
    ) {
        $this->lockFactory = $lockFactory;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $this->lockFactory) {
            return $stack->next()->handle($envelope, $stack);
        }

        $message = $envelope->getMessage();

        if (null === $envelope->last(ReceivedStamp::class)) {
            if ($message instanceof LockableMessageInterface) {
                // If we're trying to dispatch a lockable message.
                $keyResource = $message->getKey();

                if (null !== $keyResource) {
                    $key = new Key($keyResource);

                    // The acquire call must be done before stamping the message
                    // in order to have the full state of the key in the stamp.
                    $lock = $message instanceof TTLAwareLockableMessageInterface
                        ? $this->lockFactory->createLock($key, $message->getTTL(), autoRelease: false)
                        : $this->lockFactory->createLock($key, autoRelease: false);
                    $canAcquire = $lock->acquire();

                    $envelope = $envelope->with(new LockStamp($key, $message->shouldBeReleasedBeforeHandlerCall()));
                    if (!$canAcquire) {
                        return $envelope;
                    }
                }
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
        if ($stamp instanceof LockStamp && $stamp->shouldBeReleasedBeforHandlerCall() === $beforeHandlerCall) {
            $message = $envelope->getMessage();
            $lock = $message instanceof TTLAwareLockableMessageInterface
                ? $this->lockFactory->createLockFromKey($stamp->getKey(), $message->getTTL(), autoRelease: false)
                : $this->lockFactory->createLockFromKey($stamp->getKey(), autoRelease: false);
            $lock->release();
        }
    }
}
