<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Sync;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SyncMessageRetryingEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;

class SyncTransportFactoryTest extends TestCase
{
    private int $calls = 0;

    public function testCreateTransport()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $factory = new SyncTransportFactory(new MessageBus());
        $transport = $factory->createTransport('sync://', [], $serializer);
        $this->assertInstanceOf(SyncTransport::class, $transport);
    }

    public function testTransportWithoutOptionsDoesNotRetry()
    {
        $transport = $this->createFactory()->createTransport('sync://', ['transport_name' => 'foo'], $this->createStub(SerializerInterface::class));

        $this->expectException(HandlerFailedException::class);

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
        } finally {
            $this->assertSame(1, $this->calls);
        }
    }

    #[DataProvider('provideRetryConfigurations')]
    public function testRetryOption(string $dsn, array $options)
    {
        $transport = $this->createFactory()->createTransport($dsn, $options + ['transport_name' => 'foo'], $this->createStub(SerializerInterface::class));

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame(2, $this->calls);
        $this->assertNotNull($envelope->last(HandledStamp::class));
        $this->assertSame(1, RedeliveryStamp::getRetryCountFromEnvelope($envelope));
    }

    public static function provideRetryConfigurations(): iterable
    {
        yield 'dsn' => ['sync://?retry=true', []];
        yield 'dsn with 1' => ['sync://?retry=1', []];
        yield 'options' => ['sync://', ['retry' => true]];
        yield 'options as string' => ['sync://', ['retry' => 'yes']];
        yield 'options win over the dsn' => ['sync://?retry=false', ['retry' => true]];
    }

    public function testOptionsWinOverTheDsn()
    {
        $transport = $this->createFactory()->createTransport('sync://?retry=true', ['retry' => false, 'transport_name' => 'foo'], $this->createStub(SerializerInterface::class));

        $this->expectException(HandlerFailedException::class);

        try {
            $transport->send(new Envelope(new DummyMessage('Hey')));
        } finally {
            $this->assertSame(1, $this->calls);
        }
    }

    #[DataProvider('provideFailureTransportConfigurations')]
    public function testFailureTransportOption(string $dsn, array $options)
    {
        $failureTransport = new InMemoryTransport();
        $transport = $this->createFactory($failureTransport)->createTransport($dsn, $options + ['transport_name' => 'foo'], $this->createStub(SerializerInterface::class));

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame(1, $this->calls);
        $this->assertSame('sync', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertCount(1, $failureTransport->getSent());
    }

    public static function provideFailureTransportConfigurations(): iterable
    {
        yield 'dsn' => ['sync://?failure_transport=true', []];
        yield 'options' => ['sync://', ['failure_transport' => true]];
        yield 'options win over the dsn' => ['sync://?failure_transport=false', ['failure_transport' => 'on']];
    }

    public function testBothOptionsTogether()
    {
        $failureTransport = new InMemoryTransport();
        $transport = $this->createFactory($failureTransport, 5)->createTransport('sync://?retry=true&failure_transport=true', ['transport_name' => 'foo'], $this->createStub(SerializerInterface::class));

        $envelope = $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame(2, $this->calls);
        $this->assertNotNull($envelope->last(SentToFailureTransportStamp::class));
        $this->assertCount(1, $failureTransport->getSent());
    }

    #[DataProvider('provideInvalidBooleans')]
    public function testInvalidBooleansAreRejected(string $dsn, array $options, string $option)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" option of the "foo" transport must be a boolean.', $option));

        $this->createFactory()->createTransport($dsn, $options + ['transport_name' => 'foo'], $this->createStub(SerializerInterface::class));
    }

    public static function provideInvalidBooleans(): iterable
    {
        yield 'retry in the dsn' => ['sync://?retry=maybe', [], 'retry'];
        yield 'retry in the options' => ['sync://', ['retry' => 'maybe'], 'retry'];
        yield 'failure_transport in the dsn' => ['sync://?failure_transport=maybe', [], 'failure_transport'];
        yield 'failure_transport in the options' => ['sync://', ['failure_transport' => []], 'failure_transport'];
    }

    public function testRetryWithoutConfiguredStrategyIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "retry" option is enabled on the "bar" transport but no retry strategy is configured for it.');

        $this->createFactory()->createTransport('sync://?retry=true', ['transport_name' => 'bar'], $this->createStub(SerializerInterface::class));
    }

    public function testRetryWithoutLocatorIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "retry" option is enabled on the "foo" transport but no retry strategy is configured for it.');

        (new SyncTransportFactory(new MessageBus()))->createTransport('sync://?retry=true', ['transport_name' => 'foo'], $this->createStub(SerializerInterface::class));
    }

    public function testFailureTransportWithoutConfiguredFailureTransportIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "failure_transport" option is enabled on the "foo" transport but no failure transport is configured for it.');

        $this->createFactory()->createTransport('sync://?failure_transport=true', ['transport_name' => 'foo'], $this->createStub(SerializerInterface::class));
    }

    public function testMissingTransportNameIsRejectedWhenAnOptionNeedsIt()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "retry" option is enabled on the "sync" transport but no retry strategy is configured for it.');

        $this->createFactory()->createTransport('sync://?retry=true', [], $this->createStub(SerializerInterface::class));
    }

    public function testTheEventDispatcherIsPassedToTheTransport()
    {
        $events = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SyncMessageRetryingEvent::class, static function (SyncMessageRetryingEvent $event) use (&$events) { $events[] = $event; });
        $transport = $this->createFactory(eventDispatcher: $dispatcher)->createTransport('sync://?retry=true', ['transport_name' => 'foo'], $this->createStub(SerializerInterface::class));

        $transport->send(new Envelope(new DummyMessage('Hey')));

        $this->assertSame(2, $this->calls);
        $this->assertCount(1, $events);
        $this->assertSame(1, RedeliveryStamp::getRetryCountFromEnvelope($events[0]->getEnvelope()));
    }

    public function testSupports()
    {
        $factory = new SyncTransportFactory(new MessageBus());

        $this->assertTrue($factory->supports('sync://', []));
        $this->assertTrue($factory->supports('sync://?retry=true', []));
        $this->assertFalse($factory->supports('in-memory://', []));
    }

    private function createFactory(?InMemoryTransport $failureTransport = null, int $failures = 1, ?EventDispatcherInterface $eventDispatcher = null): SyncTransportFactory
    {
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [function () use ($failures) {
            if ($failures >= ++$this->calls) {
                throw new \RuntimeException('Attempt '.$this->calls);
            }
        }]]))]);

        $retryStrategies = new Container();
        $retryStrategies->set('foo', new MultiplierRetryStrategy(1));

        $failureSenders = new Container();
        if ($failureTransport) {
            $failureSenders->set('foo', $failureTransport);
        }

        return new SyncTransportFactory($bus, $retryStrategies, $failureSenders, $eventDispatcher);
    }
}
