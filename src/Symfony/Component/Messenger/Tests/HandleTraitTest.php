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
    /**
     * @expectedException \Symfony\Component\Messenger\Exception\LogicException
     * @expectedExceptionMessage You must provide a "Symfony\Component\Messenger\MessageBusInterface" instance in the "Symfony\Component\Messenger\Tests\TestQueryBus::$messageBus" property, "NULL" given.
     */
    public function testItThrowsOnNoMessageBusInstance()
    {
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
            new Envelope($query, new HandledStamp('result', 'DummyHandler::__invoke'))
        );

        $this->assertSame('result', $queryBus->query($query));
    }

    public function testHandleAcceptsEnvelopes()
    {
        $bus = $this->createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $envelope = new Envelope(new DummyMessage('Hello'), new HandledStamp('result', 'DummyHandler::__invoke'));
        $bus->expects($this->once())->method('dispatch')->willReturn($envelope);

        $this->assertSame('result', $queryBus->query($envelope));
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\LogicException
     * @expectedExceptionMessage Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" was handled zero times. Exactly one handler is expected when using "Symfony\Component\Messenger\Tests\TestQueryBus::handle()".
     */
    public function testHandleThrowsOnNoHandledStamp()
    {
        $bus = $this->createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $query = new DummyMessage('Hello');
        $bus->expects($this->once())->method('dispatch')->willReturn(new Envelope($query));

        $queryBus->query($query);
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\LogicException
     * @expectedExceptionMessage Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" was handled multiple times. Only one handler is expected when using "Symfony\Component\Messenger\Tests\TestQueryBus::handle()", got 2: "FirstDummyHandler::__invoke", "dummy_2".
     */
    public function testHandleThrowsOnMultipleHandledStamps()
    {
        $bus = $this->createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $query = new DummyMessage('Hello');
        $bus->expects($this->once())->method('dispatch')->willReturn(
            new Envelope($query, new HandledStamp('first_result', 'FirstDummyHandler::__invoke'), new HandledStamp('second_result', 'SecondDummyHandler::__invoke', 'dummy_2'))
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
