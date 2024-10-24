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
use Symfony\Component\Messenger\Middleware\LockMiddleware;
use Symfony\Component\Messenger\Stamp\LockStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

final class LockMiddlewareTest extends MiddlewareTestCase
{
    public function testLockMiddlewareIgnoreIfMessageIsNotLockable()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLock');

        $decorator = new LockMiddleware($lockFactory);

        $decorator->handle($envelope, $this->getStackMock(true));
    }

    public function testLockMiddlewareIfMessageHasKey()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, [new LockStamp(LockStamp::MODE_DISCARD, 'id')]);

        if (SemaphoreStore::isSupported()) {
            $store = new SemaphoreStore();
        } else {
            $store = new FlockStore();
        }

        $decorator = new LockMiddleware(new LockFactory($store));

        $envelope = $decorator->handle($envelope, $this->getStackMock(true));
        $this->assertNotNull($envelope->last(LockStamp::class));

        $message2 = new DummyMessage('Hello');
        $envelope2 = new Envelope($message2, [new LockStamp(LockStamp::MODE_DISCARD, 'id')]);

        $decorator->handle($envelope2, $this->getStackMock(false));

        // Simulate receiving the first message
        $envelope = $envelope->with(new ReceivedStamp('transport'));
        $decorator->handle($envelope, $this->getStackMock(true));

        $message3 = new DummyMessage('Hello');
        $envelope3 = new Envelope($message3, [new LockStamp(LockStamp::MODE_DISCARD, 'id')]);
        $decorator->handle($envelope3, $this->getStackMock(true));
    }
}
