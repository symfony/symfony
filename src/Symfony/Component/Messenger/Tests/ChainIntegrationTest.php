<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnIdleListener;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\ChainMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Worker;

class ChainIntegrationTest extends TestCase
{
    private array $handled = [];
    private array $arguments = [];

    /**
     * @var array<class-string, int> The number of times the handler of each message class throws before it succeeds
     */
    private array $failures = [];

    private MockClock $clock;
    private array $receivers = [];
    private Container $senders;
    private Container $retryStrategies;
    private InMemoryTransport $transport;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
        $this->senders = new Container();
        $this->retryStrategies = new Container();
        $this->transport = $this->addTransport('async');
    }

    public function testSynchronousChainIsHandledInOrderWithinTheDispatchCall()
    {
        $bus = $this->createBus();

        $bus->dispatch(ChainStamp::envelope($first = new DummyMessage('first'), $second = new SecondMessage(), $third = new ThirdMessage()));

        $this->assertSame([$first, $second, $third], $this->handled);
        $this->assertSame([], $this->transport->getSent());
    }

    public function testChainContinuesAfterAnAsynchronousStep()
    {
        $bus = $this->createBus([SecondMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), $third = new ThirdMessage())]);

        $this->assertSame([$first], $this->handled);
        $this->assertCount(1, $sent = $this->transport->getSent());
        $this->assertSame($second, $sent[0]->getMessage());
        $this->assertSame([$third], $sent[0]->last(ChainStamp::class)->getMessages());

        $this->runWorker($bus);

        $this->assertSame([$first, $second, $third], $this->handled);
        $this->assertCount(2, $this->transport->getAcknowledged());
        $this->assertSame([], $this->transport->get());
    }

    public function testChainTravelsThroughATransportThatUsesTheSymfonySerializer()
    {
        $this->transport = $this->addTransport('async', Serializer::create());
        $bus = $this->createBus([DummyMessage::class => ['async']]);

        $bus->dispatch(ChainStamp::envelope(new DummyMessage('first'), new SecondMessage(), new Envelope(new DummyMessage('third'), [new DelayStamp(1000)])));
        $this->runWorker($bus);
        $this->clock->sleep(2);
        $this->runWorker($bus);

        $this->assertEquals([new DummyMessage('first'), new SecondMessage(), new DummyMessage('third')], $this->handled);
        $this->assertSame([], $this->transport->get());
    }

    public function testNothingIsDispatchedWhenTheFirstStepFails()
    {
        $this->failures[DummyMessage::class] = 1;
        $bus = $this->createBus([SecondMessage::class => ['async']]);

        try {
            $bus->dispatch(new DummyMessage('first'), [new ChainStamp(new SecondMessage(), new ThirdMessage())]);
            $this->fail('The failure of the first step should have been reported.');
        } catch (HandlerFailedException) {
        }

        $this->assertSame([], $this->handled);
        $this->assertSame([], $this->transport->getSent());
    }

    public function testChainStopsWhenASynchronousStepFails()
    {
        $this->failures[SecondMessage::class] = 1;
        $bus = $this->createBus();

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp(new SecondMessage(), new ThirdMessage())]);
            $this->fail('The failure of the second step should have been reported.');
        } catch (DelayedMessageHandlingException $e) {
            $this->assertInstanceOf(HandlerFailedException::class, $e->getPrevious());
        }

        $this->assertSame([$first], $this->handled);
    }

    public function testAStepWithoutRouteIsSentToTheTransportThePreviousStepCameFromAndRetriedOnItsOwn()
    {
        $this->failures[SecondMessage::class] = 1;
        $bus = $this->createBus([DummyMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), $third = new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertSame([$first], $this->handled);
        $this->assertCount(1, $retried = $this->transport->all());
        $this->assertSame($second, $retried[0]->getMessage());
        $this->assertSame(1, $retried[0]->last(RedeliveryStamp::class)?->getRetryCount());

        $this->clock->sleep(2);
        $this->runWorker($bus);

        $this->assertSame([$first, $second, $third], $this->handled);
        $this->assertSame([], $this->transport->all());
    }

    public function testTheChainContinuesWhenTheMessageIsRetriedAfterItsNextStepCouldNotBeSent()
    {
        $flaky = $this->addTransport('flaky');
        $this->senders->set('flaky', new class($flaky) implements SenderInterface {
            private int $failures = 1;

            public function __construct(private SenderInterface $sender)
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                return 0 < $this->failures-- ? throw new TransportException('The transport is unavailable.') : $this->sender->send($envelope);
            }
        });
        $bus = $this->createBus([DummyMessage::class => ['async'], SecondMessage::class => ['flaky']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage())]);
        $this->runWorker($bus);

        $this->assertSame([$first], $this->handled);
        $this->assertCount(1, $retried = $this->transport->all());
        $this->assertSame($first, $retried[0]->getMessage());

        $this->clock->sleep(2);
        $this->runWorker($bus);
        $this->runWorker($bus, 'flaky');

        $this->assertSame($second, end($this->handled));
        $this->assertSame([], $this->transport->all());
        $this->assertSame([], $flaky->all());
    }

    public function testAStepWithoutRouteIsSentToTheSynchronousTransportThePreviousStepCameFrom()
    {
        $this->failures[SecondMessage::class] = 1;
        $buses = new Container();
        $this->senders->set('sync', new SyncTransport(new RoutableMessageBus($buses), new MultiplierRetryStrategy(1, 0, 1, 0, 0)));
        $bus = $this->createBus([DummyMessage::class => ['sync']], $buses);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage())]);

        $this->assertSame([$first, $second], $this->handled);
    }

    public function testTheChainStartsWhereTheMessageIsHandledAndNotWhereItIsRelayed()
    {
        $outbox = $this->addTransport('outbox');
        $this->senders->set('async', new OutboxSender($this->transport, $outbox, 'async'));
        $bus = $this->createBus([DummyMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage())]);
        $this->runWorker($bus, 'outbox');

        $this->assertSame([], $this->handled);
        $this->assertSame([], $outbox->all());

        $this->runWorker($bus);

        $this->assertSame([$first], $this->handled);
        $this->assertCount(1, $stored = $outbox->all(), 'the next step is sent to the transport of the previous one, through its outbox');
        $this->assertSame($second, $stored[0]->getMessage());

        $this->runWorker($bus, 'outbox');
        $this->runWorker($bus);

        $this->assertSame([$first, $second], $this->handled);
    }

    public function testTheNextStepOfAnUntrustedStepIsRefusedByASigningTransport()
    {
        $this->transport = $this->addTransport('async', new PhpSerializer());
        $signed = $this->addTransport('signed', new SigningSerializer(new PhpSerializer(), 'signing-key', ['*']));
        $bus = $this->createBus([DummyMessage::class => ['async'], SecondMessage::class => ['signed']]);

        $bus->dispatch(new DummyMessage('trusted'), [new ChainStamp(new SecondMessage()), new TransportNamesStamp([])]);

        $this->assertInstanceOf(SecondMessage::class, $signed->get()[0]->getMessage(), 'a step dispatched in this process is trusted');
        $signed->reset();

        $bus->dispatch(new DummyMessage('received'), [new ChainStamp(new SecondMessage())]);
        $this->runWorker($bus);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $refused = $signed->get()[0]->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $refused->getPrevious());
    }

    public function testAStepChosenByAnUnverifiedMessageCannotReachAHandlerThatRequiresASignature()
    {
        $this->transport = $this->addTransport('async', new PhpSerializer());
        $bus = $this->createBus();
        $this->senders->set('sync', new SyncTransport($bus));

        $bus->dispatch(new DummyMessage('trusted'), [new ChainStamp(new Envelope($command = new DummyCommand(), [new TransportNamesStamp(['sync'])]))]);

        $this->assertEquals([new DummyMessage('trusted'), $command], $this->handled, 'a step dispatched in this process is trusted');
        $this->handled = [];

        $this->transport->send(new Envelope(new DummyMessage('received'), [new ChainStamp(new Envelope(new DummyCommand(), [new TransportNamesStamp(['sync'])]))]));
        $this->runWorker($bus);

        $this->assertEquals([new DummyMessage('received')], $this->handled);
    }

    public function testTheForgedNonSendableStampsOfAStepAreNotDispatched()
    {
        $this->transport = $this->addTransport('async', new PhpSerializer());
        $bus = $this->createBus();

        $chain = (new \ReflectionClass(ChainStamp::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(ChainStamp::class, 'messages'))->setValue($chain, [new Envelope(new SecondMessage(), [new HandlerArgumentsStamp(['forged'])])]);
        $this->transport->send(new Envelope(new DummyMessage('first'), [$chain]));

        $this->runWorker($bus);

        $this->assertEquals([new DummyMessage('first'), new SecondMessage()], $this->handled);
        $this->assertSame([[], []], $this->arguments);
    }

    private function addTransport(string $name, ?SerializerInterface $serializer = null): InMemoryTransport
    {
        $this->receivers[$name] = $transport = new InMemoryTransport($serializer, $this->clock);
        $this->senders->set($name, $transport);
        $this->retryStrategies->set($name, new MultiplierRetryStrategy(3, 1000, 1, 0, 0));

        return $transport;
    }

    /**
     * @param array<class-string, list<string>> $routing The transports of each message class
     */
    private function createBus(array $routing = [], Container $buses = new Container()): MessageBus
    {
        $handler = function (object $message, mixed ...$arguments): void {
            if (0 < ($this->failures[$message::class] ?? 0)) {
                --$this->failures[$message::class];

                throw new \RuntimeException(\sprintf('Handling "%s" failed.', $message::class));
            }

            $this->handled[] = $message;
            $this->arguments[] = $arguments;
        };
        $handlersLocator = new HandlersLocator([
            DummyMessage::class => [$handler],
            SecondMessage::class => [$handler],
            ThirdMessage::class => [$handler],
            DummyCommand::class => [new HandlerDescriptor($handler, ['sign' => true])],
        ]);

        $sendersLocator = new SendersLocator($routing, $this->senders);

        $bus = new MessageBus([
            new AddBusNameStampMiddleware('the_bus'),
            new DispatchAfterCurrentBusMiddleware(),
            new FailedMessageProcessingMiddleware(),
            new SendMessageMiddleware($sendersLocator),
            new ChainMiddleware(new RoutableMessageBus($buses), $sendersLocator),
            new HandleMessageMiddleware($handlersLocator),
        ]);
        $buses->set('the_bus', $bus);

        return $bus;
    }

    private function runWorker(MessageBus $bus, string $transportName = 'async'): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($this->senders, $this->retryStrategies));
        $dispatcher->addSubscriber(new StopWorkerOnIdleListener());

        (new Worker([$transportName => $this->receivers[$transportName]], $bus, $dispatcher, null, null, $this->clock))->run();
    }
}
