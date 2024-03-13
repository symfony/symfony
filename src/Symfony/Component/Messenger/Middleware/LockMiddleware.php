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
use Symfony\Component\Messenger\Message\LockableMessageInterface;
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

        // If we're trying to dispatch a lockable message.
        if ($message instanceof LockableMessageInterface && null === $envelope->last(ReceivedStamp::class)) {
            $key = $message->getKey();

            if (null !== $key) {
                // The acquire call must be done before stamping the message
                // in order to have the full state of the key in the stamp.
                $canAcquire = $this->lockFactory->createLock($key, autoRelease: false)->acquire();

                $envelope = $envelope->with(new LockStamp($key));
                if (!$canAcquire) {
                    return $envelope;
                }
            }
        }

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } finally {
            // If we've received a lockable message, we're releasing it.
            if (null !== $envelope->last(ReceivedStamp::class)) {
                $stamp = $envelope->last(LockStamp::class);
                if ($stamp instanceof LockStamp) {
                    $this->lockFactory->createLockFromKey($stamp->getKey(), autoRelease: false)->release();
                }
            }
        }

        return $envelope;
    }
}
