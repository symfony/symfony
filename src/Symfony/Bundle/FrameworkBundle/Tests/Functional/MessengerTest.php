<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\RecordingMessageHandler;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\SecondMessage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class MessengerTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        RecordingMessageHandler::$handled = [];
        RecordingMessageHandler::$attempts = 0;
        RecordingMessageHandler::$failures = 0;
    }

    public function testQueuedMessagesAreConsumedThroughTheBus()
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch($foo = new FooMessage());
        $bus->dispatch($bar = new BarMessage());

        $this->assertInstanceOf(InMemoryTransport::class, $this->getMessengerTransport('async'));
        $this->assertQueuedMessageCount(2, 'async');
        $this->assertQueuedMessageCount(1, 'async', FooMessage::class);
        $this->assertQueuedMessageCount(0, 'async', SecondMessage::class);
        $this->assertSame([$foo, $bar], array_map(static fn (Envelope $envelope) => $envelope->getMessage(), $this->getQueuedMessages('async')));
        $this->assertSame([], RecordingMessageHandler::$handled);

        $this->assertSame(2, $this->consumeQueuedMessages('async'));

        $this->assertQueuedMessageCount(0, 'async');
        $this->assertSame([], $this->getQueuedMessages('async'));
        $this->assertSame([$foo, $bar], RecordingMessageHandler::$handled);

        $this->assertSame(0, $this->consumeQueuedMessages('async'));

        $this->assertSame([$foo, $bar], RecordingMessageHandler::$handled, 'Consuming an empty queue returns without handling anything');
    }

    public function testConsumeQueuedMessagesWithLimit()
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch($foo1 = new FooMessage());
        $bus->dispatch($foo2 = new FooMessage());
        $bus->dispatch($foo3 = new FooMessage());

        $this->consumeQueuedMessages('async', 2);

        $this->assertQueuedMessageCount(1, 'async');
        $this->assertSame([$foo1, $foo2], RecordingMessageHandler::$handled);

        $this->consumeQueuedMessages('async', 2);

        $this->assertQueuedMessageCount(0, 'async');
        $this->assertSame([$foo1, $foo2, $foo3], RecordingMessageHandler::$handled);
    }

    public function testConsumeQueuedMessagesReplaysARetryUntilItSucceeds()
    {
        RecordingMessageHandler::$failures = 1;
        self::getContainer()->get(MessageBusInterface::class)->dispatch($foo = new FooMessage());

        $this->assertSame(1, $this->consumeQueuedMessages('async'));

        // the retry was consumed without waiting for the 10 seconds of the retry strategy
        $this->assertSame(2, RecordingMessageHandler::$attempts);
        $this->assertSame([$foo], RecordingMessageHandler::$handled);
        $this->assertQueuedMessageCount(0, 'async');
    }

    public function testConsumeQueuedMessagesRethrowsTheFailureThatExhaustsTheRetries()
    {
        RecordingMessageHandler::$failures = \PHP_INT_MAX;
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new FooMessage());

        try {
            $this->consumeQueuedMessages('async');
            $this->fail('The failure that exhausts the retries should have been rethrown.');
        } catch (HandlerFailedException $e) {
            $this->assertSame('Handling failed.', array_values($e->getWrappedExceptions())[0]->getMessage());
        }

        // the default strategy allows three retries, and none of them waited
        $this->assertSame(4, RecordingMessageHandler::$attempts);
        $this->assertQueuedMessageCount(0, 'async');
        $this->assertSame([], RecordingMessageHandler::$handled);
    }

    public function testConsumeQueuedMessagesLeavesAMessageDelayedByTheApplication()
    {
        self::getContainer()->get(MessageBusInterface::class)->dispatch(new FooMessage(), [new DelayStamp(3600000)]);

        $this->assertSame(0, $this->consumeQueuedMessages('async'));

        $this->assertQueuedMessageCount(1, 'async');
        $this->assertSame(0, RecordingMessageHandler::$attempts);
    }

    public function testAssertQueuedMessageCountRejectsAnUnknownMessageClass()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The message class "App\Message\Unknown" given to assertQueuedMessageCount() does not exist.');

        $this->assertQueuedMessageCount(0, 'async', 'App\Message\Unknown');
    }

    public function testGetMessengerTransportRequiresAnInMemoryTransport()
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The "sync" Messenger transport is not an in-memory transport. Configure "in-memory://" as its DSN in the test environment to make queued message assertions.');

        $this->getMessengerTransport('sync');
    }

    public function testGetMessengerTransportRequiresAConfiguredTransport()
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The "unknown" Messenger transport is not registered. Did you forget to configure it under "framework.messenger.transports"?');

        $this->getMessengerTransport('unknown');
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['test_case' => 'Messenger'] + $options);
    }
}
