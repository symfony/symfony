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
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\ChainMiddleware;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

class ChainIntegrationTest extends TestCase
{
    private array $handled = [];
    private InMemoryTransport $transport;

    protected function setUp(): void
    {
        $this->handled = [];
        $this->transport = new InMemoryTransport();
    }

    public function testSynchronousChainIsHandledInOrderWithinTheDispatchCall()
    {
        $bus = $this->createBus();

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), $third = new ThirdMessage())]);

        $this->assertSame([$first, $second, $third], $this->handled);
        $this->assertSame([], $this->transport->getSent());
    }

    public function testChainContinuesAfterAnAsynchronousStep()
    {
        $bus = $this->createBus(routing: [SecondMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), $third = new ThirdMessage())]);

        $this->assertSame([$first], $this->handled);
        $this->assertCount(1, $sent = $this->transport->getSent());
        $this->assertSame($second, $sent[0]->getMessage());
        $this->assertSame([$third], $sent[0]->last(ChainStamp::class)->getMessages());

        $this->runWorker($bus);

        $this->assertSame([$first, $second, $third], $this->handled);
        $this->assertCount(1, $this->transport->getAcknowledged());
        $this->assertSame([], $this->transport->get());
    }

    public function testNothingIsDispatchedWhenTheFirstStepFails()
    {
        $bus = $this->createBus([DummyMessage::class], [SecondMessage::class => ['async']]);

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
        $bus = $this->createBus([SecondMessage::class]);

        try {
            $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp(new SecondMessage(), new ThirdMessage())]);
            $this->fail('The failure of the second step should have been reported.');
        } catch (DelayedMessageHandlingException $e) {
            $this->assertInstanceOf(HandlerFailedException::class, $e->getPrevious());
        }

        $this->assertSame([$first], $this->handled);
    }

    public function testAsynchronousStepFailsWhenTheNextSynchronousStepFails()
    {
        $bus = $this->createBus([ThirdMessage::class], [SecondMessage::class => ['async']]);

        $bus->dispatch($first = new DummyMessage('first'), [new ChainStamp($second = new SecondMessage(), new ThirdMessage())]);
        $this->runWorker($bus);

        $this->assertSame([$first, $second], $this->handled);
        $this->assertSame([], $this->transport->getAcknowledged());
        $this->assertCount(1, $rejected = $this->transport->getRejected());
        $this->assertSame($second, $rejected[0]->getMessage());
    }

    /**
     * @param class-string[]                    $failing The messages whose handler throws
     * @param array<class-string, list<string>> $routing The messages sent to the "async" transport
     */
    private function createBus(array $failing = [], array $routing = []): MessageBus
    {
        $handler = function (object $message) use ($failing): void {
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

        $buses = new Container();
        $bus = new MessageBus([
            new AddBusNameStampMiddleware('the_bus'),
            new DispatchAfterCurrentBusMiddleware(),
            new SendMessageMiddleware(new SendersLocator($routing, $senders)),
            new ChainMiddleware(new RoutableMessageBus($buses)),
            new HandleMessageMiddleware($handlersLocator),
        ]);
        $buses->set('the_bus', $bus);

        return $bus;
    }

    private function runWorker(MessageBus $bus): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        (new Worker(['async' => $this->transport], $bus, $dispatcher))->run();
    }
}
