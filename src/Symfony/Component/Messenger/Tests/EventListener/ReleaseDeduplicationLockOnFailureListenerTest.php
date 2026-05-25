<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\ReleaseDeduplicationLockOnFailureListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

final class ReleaseDeduplicationLockOnFailureListenerTest extends TestCase
{
    public function testLockIsReleasedWhenMessageWillNotRetry()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLockFromKey')->willReturn($lock);

        $envelope = new Envelope(new \stdClass(), [new DeduplicateStamp('id')]);
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception('boom'));

        (new ReleaseDeduplicationLockOnFailureListener($lockFactory))->onMessageFailed($event);
    }

    public function testLockIsNotReleasedWhenMessageWillRetry()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLockFromKey');

        $envelope = new Envelope(new \stdClass(), [new DeduplicateStamp('id')]);
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception('boom'));
        $event->setForRetry();

        (new ReleaseDeduplicationLockOnFailureListener($lockFactory))->onMessageFailed($event);
    }

    public function testNoopWhenEnvelopeHasNoDeduplicateStamp()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLockFromKey');

        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception('boom'));

        (new ReleaseDeduplicationLockOnFailureListener($lockFactory))->onMessageFailed($event);
    }

    public function testLockIsNotReleasedWhenOnlyDeduplicateInQueue()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLockFromKey');

        $envelope = new Envelope(new \stdClass(), [new DeduplicateStamp('id', onlyDeduplicateInQueue: true)]);
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception('boom'));

        (new ReleaseDeduplicationLockOnFailureListener($lockFactory))->onMessageFailed($event);
    }

    public function testListenerRunsAfterRetryListener()
    {
        $retryPriority = SendFailedMessageForRetryListener::getSubscribedEvents()[WorkerMessageFailedEvent::class][1];
        $ownPriority = ReleaseDeduplicationLockOnFailureListener::getSubscribedEvents()[WorkerMessageFailedEvent::class][1];

        $this->assertLessThan($retryPriority, $ownPriority, 'Must run after SendFailedMessageForRetryListener so willRetry() is set.');
    }
}
