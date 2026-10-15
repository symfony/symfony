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

use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Middleware\InboxMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyInbox;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class InboxMiddlewareTest extends MiddlewareTestCase
{
    private DummyInbox $inbox;
    private InboxMiddlewareTestLogger $logger;
    private InboxMiddleware $middleware;

    protected function setUp(): void
    {
        $this->inbox = new DummyInbox();
        $this->logger = new InboxMiddlewareTestLogger();
        $this->middleware = new InboxMiddleware(new ServiceLocator(['orders' => fn () => $this->inbox]), ['failed'], $this->logger);
    }

    public function testAReceivedMessageIsRecordedBeforeItIsHandled()
    {
        $envelope = new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('orders'), new MessageIdStamp('id-1')]);

        $this->middleware->handle($envelope, $this->getStackMock(true));

        $this->assertSame([['orders', 'id-1']], $this->inbox->records);
    }

    public function testTheHandlersOfAMessageRecordedAlreadyAreSkipped()
    {
        $envelope = new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('orders'), new MessageIdStamp('id-1')]);
        $this->inbox->records[] = ['orders', 'id-1'];

        $this->assertSame($envelope, $this->middleware->handle($envelope, $this->getStackMock(false)));
        $this->assertSame(['info: Skipping message Symfony\Component\Messenger\Tests\Fixtures\DummyMessage, recorded already by the inbox of the "orders" transport'], $this->logger->logs);
    }

    public function testADispatchedMessageIsNotRecorded()
    {
        $this->middleware->handle(new Envelope(new DummyMessage('Hey'), [new MessageIdStamp('id-1')]), $this->getStackMock(true));

        $this->assertSame([], $this->inbox->records);
    }

    public function testAMessageFromATransportWithoutInboxIsNotRecorded()
    {
        $this->middleware->handle(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('invoices'), new MessageIdStamp('id-1')]), $this->getStackMock(true));

        $this->assertSame([], $this->inbox->records);
    }

    public function testAMessageWithoutIdIsHandledWithoutBeingRecorded()
    {
        $this->middleware->handle(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('orders')]), $this->getStackMock(true));

        $this->assertSame([], $this->inbox->records);
        $this->assertSame(['warning: The inbox of the "orders" transport cannot record message Symfony\Component\Messenger\Tests\Fixtures\DummyMessage without an id'], $this->logger->logs);
    }

    public function testAMessageRetriedFromAFailureTransportIsRecordedForTheTransportItFailedOn()
    {
        $envelope = new Envelope(new DummyMessage('Hey'), [new SentToFailureTransportStamp('orders'), new ReceivedStamp('failed'), new MessageIdStamp('id-1')]);

        $this->middleware->handle($envelope, $this->getStackMock(true));

        $this->assertSame([['orders', 'id-1']], $this->inbox->records);
        $this->assertSame('orders', $this->middleware->getConsumer($envelope));
    }

    public function testTheTransportItFailedOnIsTrustedOnlyFromAFailureTransport()
    {
        $envelope = new Envelope(new DummyMessage('Hey'), [new SentToFailureTransportStamp('orders'), new ReceivedStamp('invoices'), new MessageIdStamp('id-1')]);

        $this->middleware->handle($envelope, $this->getStackMock(true));

        $this->assertSame([], $this->inbox->records);
        $this->assertNull($this->middleware->getConsumer($envelope));
    }

    public function testAMessageThatABatchHandlerBuffersIsForgotten()
    {
        $envelope = new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('orders'), new MessageIdStamp('id-1')]);
        $buffered = $envelope->with(new NoAutoAckStamp(new HandlerDescriptor(static fn () => null)));

        $next = $this->createMock(MiddlewareInterface::class);
        $next->expects($this->once())->method('handle')->willReturnCallback(fn (Envelope $envelope, StackInterface $stack): Envelope => $this->inbox->records ? $buffered : $envelope);

        $this->assertSame($buffered, $this->middleware->handle($envelope, new StackMiddleware($next)));
        $this->assertSame([], $this->inbox->records);
    }

    public function testFlushingBatchHandlersRecordsNothing()
    {
        $envelope = new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('orders'), new MessageIdStamp('id-1'), new FlushBatchHandlersStamp(false)]);

        $this->middleware->handle($envelope, $this->getStackMock(true));

        $this->assertSame([], $this->inbox->records);
        $this->assertNull($this->middleware->getConsumer($envelope));
    }

    public function testGetConsumer()
    {
        $this->assertSame('orders', $this->middleware->getConsumer(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('orders'), new MessageIdStamp('id-1')])));
        $this->assertNull($this->middleware->getConsumer(new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('orders')])));
        $this->assertNull($this->middleware->getConsumer(new Envelope(new DummyMessage('Hey'), [new MessageIdStamp('id-1')])));
    }
}

class InboxMiddlewareTestLogger extends AbstractLogger
{
    public array $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = $level.': '.strtr($message, array_map(static fn ($value) => (string) $value, array_combine(array_map(static fn ($key) => '{'.$key.'}', array_keys($context)), $context)));
    }
}
