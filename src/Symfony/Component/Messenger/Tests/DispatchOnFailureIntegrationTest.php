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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\DispatchOnFailureListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\ChainMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchOnFailureMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

class DispatchOnFailureIntegrationTest extends TestCase
{
    private array $handled = [];
    private array $attempted = [];
    private array $failureEnvelopes = [];
    private InMemoryTransport $transport;

    protected function setUp(): void
    {
        $this->handled = [];
        $this->attempted = [];
        $this->failureEnvelopes = [];
        $this->transport = new InMemoryTransport();
    }

    public function testFailureMessageIsDispatchedWhenASingleMessageFailsSynchronously()
    {
        $bus = $this->createBus([DummyMessage::class]);

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new ThirdMessage())]);
            $this->fail('The failure of the message should have been reported.');
        } catch (HandlerFailedException) {
        }

        $this->assertSame([$failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($first, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
    }

    public function testFailureMessageIsDispatchedOnceWhenASynchronousStepOfAChainFails()
    {
        $bus = $this->createBus([SecondMessage::class]);

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), new ThirdMessage()), new DispatchOnFailureStamp($failure = new DummyMessage('failure'))]);
            $this->fail('The failure of the second step should have been reported.');
        } catch (DelayedMessageHandlingException) {
        }

        $this->assertSame([$first, $failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
    }

    public function testFailureMessageIsDispatchedOnceWhenAnAsynchronousStepOfAChainFailsInAWorker()
    {
        $bus = $this->createBus([SecondMessage::class], [SecondMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), new ThirdMessage()), new DispatchOnFailureStamp($failure = new DummyMessage('failure'))]);

        $this->assertSame([$first], $this->handled);
        $this->assertSame([], $this->failureEnvelopes);

        $this->runWorker($bus);

        $this->assertSame([$first, $failure], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
        $this->assertSame($second, $this->failureEnvelopes[0]->last(FailedMessageStamp::class)->getMessage());
        $this->assertSame(\RuntimeException::class, $this->failureEnvelopes[0]->last(ErrorDetailsStamp::class)->getExceptionClass());
        $this->assertCount(1, $this->transport->getRejected());
    }

    public function testNothingIsDispatchedWhenTheChainSucceeds()
    {
        $bus = $this->createBus(routing: [SecondMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), $third = new ThirdMessage()), new DispatchOnFailureStamp(new DummyMessage('failure'))]);
        $this->runWorker($bus);

        $this->assertSame([$first, $second, $third], $this->handled);
        $this->assertSame([], $this->failureEnvelopes);
    }

    public function testAFailingFailureMessageDoesNotLoopAndDoesNotHideTheOriginalFailure()
    {
        $bus = $this->createBus([DummyMessage::class, SecondMessage::class]);

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new DispatchOnFailureStamp($failure = new SecondMessage())]);
            $this->fail('The failure of the first message should have been reported.');
        } catch (HandlerFailedException $e) {
            $this->assertSame($first, $e->getEnvelope()->getMessage());
            $this->assertSame(\sprintf('Handling "%s" failed.', DummyMessage::class), $e->getPrevious()->getMessage());
        }

        $this->assertSame([$first, $failure], $this->attempted);
        $this->assertSame([], $this->handled);
        $this->assertCount(1, $this->failureEnvelopes);
    }

    /**
     * @param class-string[]                    $failing The messages whose handler throws
     * @param array<class-string, list<string>> $routing The messages sent to the "async" transport
     */
    private function createBus(array $failing = [], array $routing = []): MessageBus
    {
        $handler = function (object $message) use ($failing): void {
            $this->attempted[] = $message;

            if (\in_array($message::class, $failing, true)) {
                throw new \RuntimeException(\sprintf('Handling "%s" failed.', $message::class));
            }

            $this->handled[] = $message;
        };
        $handlersLocator = new HandlersLocator([
            DummyMessage::class => [$handler],
            SecondMessage::class => [$handler],
            ThirdMessage::class => [$handler],
        ]);

        $senders = new Container();
        $senders->set('async', $this->transport);

        $recorder = new class($this->failureEnvelopes) implements MiddlewareInterface {
            public function __construct(private array &$failureEnvelopes)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                if (null !== $envelope->last(FailedMessageStamp::class)) {
                    $this->failureEnvelopes[] = $envelope;
                }

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $buses = new Container();
        $routableBus = new RoutableMessageBus($buses);
        $bus = new MessageBus([
            new AddBusNameStampMiddleware('the_bus'),
            $recorder,
            new DispatchAfterCurrentBusMiddleware(),
            new DispatchOnFailureMiddleware($routableBus),
            new SendMessageMiddleware(new SendersLocator($routing, $senders)),
            new ChainMiddleware($routableBus),
            new HandleMessageMiddleware($handlersLocator),
        ]);
        $buses->set('the_bus', $bus);

        return $bus;
    }

    private function runWorker(MessageBus $bus): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $dispatcher->addSubscriber(new DispatchOnFailureListener($bus));

        (new Worker(['async' => $this->transport], $bus, $dispatcher))->run();
    }
}
