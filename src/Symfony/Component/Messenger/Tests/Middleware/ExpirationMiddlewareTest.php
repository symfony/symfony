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

use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\DeduplicateMiddleware;
use Symfony\Component\Messenger\Middleware\ExpirationMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\ExpirationStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

final class ExpirationMiddlewareTest extends MiddlewareTestCase
{
    public function testMessageWithoutExpirationIsHandled()
    {
        $envelope = new Envelope(new DummyMessage('Hello'));

        $middleware = new ExpirationMiddleware(new MockClock('2026-10-20 12:00:00'));

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testMessageIsHandledUntilItsDeadline()
    {
        $envelope = new Envelope(new DummyMessage('Hello'), [new ExpirationStamp(new \DateTimeImmutable('2026-10-20 12:00:01'))]);

        $middleware = new ExpirationMiddleware(new MockClock('2026-10-20 12:00:00'));

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock()));
    }

    public function testExpiredMessageIsDiscarded()
    {
        $envelope = new Envelope(new DummyMessage('Hello'), [new ExpirationStamp(new \DateTimeImmutable('2026-10-20 11:59:59'))]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Discarding message {class} expired on {expiration}',
                $this->callback(static fn (array $context) => DummyMessage::class === $context['class'] && str_starts_with($context['expiration'], '2026-10-20T11:59:59')),
            );

        $middleware = new ExpirationMiddleware(new MockClock('2026-10-20 12:00:00'), $logger);

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock(false)));
    }

    public function testMessageExpiringAtTheCurrentTimeIsDiscarded()
    {
        $envelope = new Envelope(new DummyMessage('Hello'), [new ExpirationStamp(new \DateTimeImmutable('2026-10-20 12:00:00'))]);

        $middleware = new ExpirationMiddleware(new MockClock('2026-10-20 12:00:00'));

        $this->assertSame($envelope, $middleware->handle($envelope, $this->getStackMock(false)));
    }

    public function testDiscardingAnExpiredMessageReleasesItsDeduplicationLock()
    {
        $handled = [];
        $clock = new MockClock('2026-10-20 12:00:00');
        $bus = new MessageBus([
            new DeduplicateMiddleware(new LockFactory(new InMemoryStore())),
            new ExpirationMiddleware($clock),
            new HandleMessageMiddleware(new HandlersLocator([
                DummyMessage::class => [static function (DummyMessage $message) use (&$handled) { $handled[] = $message->getMessage(); }],
            ])),
        ]);

        $expiring = new Envelope(new DummyMessage('first'), [
            new DeduplicateStamp('order-42'),
            new ExpirationStamp($clock->now()->modify('+10 seconds')),
        ]);

        $bus->dispatch($expiring);

        // the deadline passes while the message waits in the transport
        $clock->sleep(20);
        $bus->dispatch($expiring->with(new ReceivedStamp('async')));

        // the lock of the discarded message must not swallow the next one of the same key
        $bus->dispatch(new Envelope(new DummyMessage('second'), [
            new DeduplicateStamp('order-42'),
            new ExpirationStamp($clock->now()->modify('+120 seconds')),
        ]));

        $this->assertSame(['first', 'second'], $handled);
    }

    public function testTheDeadlineSurvivesSerialization()
    {
        $stamp = new ExpirationStamp($expiresAt = new \DateTimeImmutable('2026-10-20 11:59:59.123456'));

        $this->assertEquals($expiresAt, unserialize(serialize($stamp))->getExpiresAt());
    }
}
