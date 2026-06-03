<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

/**
 * Releases the deduplication lock when a handled message definitively fails.
 *
 * The {@see \Symfony\Component\Messenger\Middleware\DeduplicateMiddleware}
 * keeps the lock held when a handler throws, so that a message still being
 * retried cannot be enqueued again. Once the retry flow has decided not to
 * retry (retries exhausted, unrecoverable exception, or no retry strategy),
 * the lock must be released to unblock future messages sharing the same key.
 *
 * Caveat: a message moved to a failure transport and later replayed via
 * messenger:failed:retry will not be deduplicated against a parallel dispatch
 * of the same key, since the lock is released here on definitive failure.
 *
 * Security note: the key released here is read from the DeduplicateStamp that
 * travels with the envelope and is not authenticated. An actor with queue
 * write access can target the deduplication key of another in-flight message
 * by enqueueing a crafted, terminally-failing envelope. See DeduplicateStamp:
 * deduplication keys are a best-effort idempotency primitive, not a
 * correctness primitive against a hostile queue producer.
 */
final class ReleaseDeduplicationLockOnFailureListener implements EventSubscriberInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        if (!$stamp = $event->getEnvelope()->last(DeduplicateStamp::class)) {
            return;
        }

        if ($stamp->onlyDeduplicateInQueue()) {
            return;
        }

        $this->lockFactory->createLockFromKey($stamp->getKey())->release();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must have lower priority than SendFailedMessageForRetryListener (100) so willRetry() is already set
            WorkerMessageFailedEvent::class => ['onMessageFailed', 0],
        ];
    }
}
