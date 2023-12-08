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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\SingleHandlingTrait;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class SingleHandlingTraitTest extends TestCase
{
    public function testItThrowsOnNoMessageBusInstance()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You must provide a "Symfony\Component\Messenger\MessageBusInterface" instance in the "Symfony\Component\Messenger\Tests\SingleHandlerBus::$messageBus" property, but that property has not been initialized yet.');
        $singleHandlerBus = new SingleHandlerBus(null);
        $message = new DummyMessage('Hello');

        $singleHandlerBus->dispatch($message);
    }

    public function testHandleReturnsHandledStampResult()
    {
        $bus = $this->createMock(MessageBus::class);
        $singleHandlerBus = new SingleHandlerBus($bus);

        $message = new DummyMessage('Hello');
        $bus->expects($this->once())->method('dispatch')->willReturn(
            new Envelope($message, [new HandledStamp('result', 'DummyHandler::__invoke')])
        );

        $this->assertSame('result', $singleHandlerBus->dispatch($message));
    }

    public function testHandleAcceptsEnvelopes()
    {
        $bus = $this->createMock(MessageBus::class);
        $singleHandlerBus = new SingleHandlerBus($bus);

        $envelope = new Envelope(new DummyMessage('Hello'), [new HandledStamp('result', 'DummyHandler::__invoke')]);
        $bus->expects($this->once())->method('dispatch')->willReturn($envelope);

        $this->assertSame('result', $singleHandlerBus->dispatch($envelope));
    }

    public function testHandleThrowsOnNoHandledStamp()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" was handled zero times. Exactly one handler is expected when using "Symfony\Component\Messenger\Tests\SingleHandlerBus::handle()".');
        $bus = $this->createMock(MessageBus::class);
        $singleHandlerBus = new SingleHandlerBus($bus);

        $message = new DummyMessage('Hello');
        $bus->expects($this->once())->method('dispatch')->willReturn(new Envelope($message));

        $singleHandlerBus->dispatch($message);
    }

    public function testHandleThrowsOnMultipleHandledStamps()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" was handled multiple times. Only one handler is expected when using "Symfony\Component\Messenger\Tests\SingleHandlerBus::handle()", got 2: "FirstDummyHandler::__invoke", "SecondDummyHandler::__invoke".');
        $bus = $this->createMock(MessageBus::class);
        $singleHandlerBus = new SingleHandlerBus($bus);

        $message = new DummyMessage('Hello');
        $bus->expects($this->once())->method('dispatch')->willThrowException(
            new HandlerFailedException(
                new Envelope($message, [new HandledStamp('first_result', 'FirstDummyHandler::__invoke')]),
                ['SecondDummyHandler::__invoke' => new \RuntimeException('SecondDummyHandler failed.')]
            )
        );

        $singleHandlerBus->dispatch($message);
    }

    public function testHandleThrowsWrappedException()
    {
        $bus = $this->createMock(MessageBus::class);
        $singleHandlerBus = new SingleHandlerBus($bus);

        $message = new DummyMessage('Hello');
        $wrappedException = new \RuntimeException('Handler failed.');
        $bus->expects($this->once())->method('dispatch')->willThrowException(
            new HandlerFailedException(
                new Envelope($message),
                ['DummyHandler::__invoke' => new \RuntimeException('Handler failed.')]
            )
        );

        $this->expectException($wrappedException::class);
        $this->expectExceptionMessage($wrappedException->getMessage());

        $singleHandlerBus->dispatch($message);
    }
}

class SingleHandlerBus
{
    use SingleHandlingTrait;

    public function __construct(?MessageBusInterface $messageBus)
    {
        if ($messageBus) {
            $this->messageBus = $messageBus;
        }
    }

    public function dispatch($message): string
    {
        return $this->handle($message);
    }
}
