<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\DeduplicateMiddleware;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

final class DeduplicateMiddlewareTest extends MiddlewareTestCase
{
    public function testDeduplicateMiddlewareIgnoreIfMessageIsNotLockable()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLockFromKey');

        $decorator = new DeduplicateMiddleware($lockFactory);

        $decorator->handle($envelope, $this->getStackMock(true));
    }

    public function testDeduplicateMiddlewareIfMessageHasKey()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, [new DeduplicateStamp('id')]);

        if (SemaphoreStore::isSupported()) {
            $store = new SemaphoreStore();
        } else {
            $store = new FlockStore();
        }

        $decorator = new DeduplicateMiddleware(new LockFactory($store));

        $envelope = $decorator->handle($envelope, $this->getStackMock(true));
        $this->assertNotNull($envelope->last(DeduplicateStamp::class));

        $message2 = new DummyMessage('Hello');
        $envelope2 = new Envelope($message2, [new DeduplicateStamp('id')]);

        $decorator->handle($envelope2, $this->getStackMock(false));

        // Simulate receiving the first message
        $envelope = $envelope->with(new ReceivedStamp('transport'));
        $decorator->handle($envelope, $this->getStackMock(true));

        $message3 = new DummyMessage('Hello');
        $envelope3 = new Envelope($message3, [new DeduplicateStamp('id')]);
        $decorator->handle($envelope3, $this->getStackMock(true));
    }

    public function testLockIsNotReleasedWhenHandlerThrows()
    {
        $store = SemaphoreStore::isSupported() ? new SemaphoreStore() : new FlockStore();
        $decorator = new DeduplicateMiddleware(new LockFactory($store));

        // Enqueue step — acquires the lock.
        $enqueue = new Envelope(new DummyMessage('Hello'), [new DeduplicateStamp('id')]);
        $decorator->handle($enqueue, $this->getStackMock(true));

        // Consumer step — handler throws; the lock must NOT be released so the
        // failed message keeps its deduplication guarantee while waiting for retry.
        $consumed = $enqueue->with(new ReceivedStamp('transport'));

        try {
            $decorator->handle($consumed, $this->getThrowingStackMock());
            $this->fail('The handler exception must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Thrown from next middleware.', $e->getMessage());
        }

        // A new enqueue with the same key must be swallowed (lock still held).
        $duplicate = new Envelope(new DummyMessage('Hello'), [new DeduplicateStamp('id')]);
        $decorator->handle($duplicate, $this->getStackMock(false));

        // Release the lock so the test leaves no stale state for re-runs.
        $decorator->handle($consumed, $this->getStackMock(true));
    }
}
