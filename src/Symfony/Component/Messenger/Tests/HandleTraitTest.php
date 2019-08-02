<?php

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandleTraitTest extends TestCase
{
    public function testItThrowsOnNoMessageBusInstance()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\LogicException');
        $this->expectExceptionMessage('You must provide a "Symfony\Component\Messenger\MessageBusInterface" instance in the "Symfony\Component\Messenger\Tests\TestQueryBus::$messageBus" property, "NULL" given.');
        $queryBus = new TestQueryBus(null);
        $query = new DummyMessage('Hello');

        $queryBus->query($query);
    }

    public function testHandleReturnsHandledStampResult()
    {
        $bus = $this->createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $query = new DummyMessage('Hello');
        $bus->expects($this->once())->method('dispatch')->willReturn(
            new Envelope($query, [new HandledStamp('result', 'DummyHandler::__invoke')])
        );

        $this->assertSame('result', $queryBus->query($query));
    }

    public function testHandleAcceptsEnvelopes()
    {
        $bus = $this->createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $envelope = new Envelope(new DummyMessage('Hello'), [new HandledStamp('result', 'DummyHandler::__invoke')]);
        $bus->expects($this->once())->method('dispatch')->willReturn($envelope);

        $this->assertSame('result', $queryBus->query($envelope));
    }

    public function testHandleThrowsOnNoHandledStamp()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\LogicException');
        $this->expectExceptionMessage('Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" was handled zero times. Exactly one handler is expected when using "Symfony\Component\Messenger\Tests\TestQueryBus::handle()".');
        $bus = $this->createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $query = new DummyMessage('Hello');
        $bus->expects($this->once())->method('dispatch')->willReturn(new Envelope($query));

        $queryBus->query($query);
    }

    public function testHandleThrowsOnMultipleHandledStamps()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\LogicException');
        $this->expectExceptionMessage('Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" was handled multiple times. Only one handler is expected when using "Symfony\Component\Messenger\Tests\TestQueryBus::handle()", got 2: "FirstDummyHandler::__invoke", "SecondDummyHandler::__invoke".');
        $bus = $this->createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $query = new DummyMessage('Hello');
        $bus->expects($this->once())->method('dispatch')->willReturn(
            new Envelope($query, [new HandledStamp('first_result', 'FirstDummyHandler::__invoke'), new HandledStamp('second_result', 'SecondDummyHandler::__invoke')])
        );

        $queryBus->query($query);
    }
}

class TestQueryBus
{
    use HandleTrait;

    public function __construct(?MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function query($query): string
    {
        return $this->handle($query);
    }
}
