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
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandleTraitTest extends TestCase
{
    public function testItThrowsOnNoMessageBusInstance()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('You must provide a "Symfony\Component\Messenger\MessageBusInterface" instance in the "Symfony\Component\Messenger\Tests\TestQueryBus::$messageBus" property, "null" given.');
        $queryBus = new TestQueryBus(null);
        $query = new DummyMessage('Hello');

        $queryBus->query($query);
    }

    public function testHandleReturnsHandledStampResult()
    {
        $bus = self::createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $query = new DummyMessage('Hello');
        $bus->expects(self::once())->method('dispatch')->willReturn(
            new Envelope($query, [new HandledStamp('result', 'DummyHandler::__invoke')])
        );

        self::assertSame('result', $queryBus->query($query));
    }

    public function testHandleAcceptsEnvelopes()
    {
        $bus = self::createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $envelope = new Envelope(new DummyMessage('Hello'), [new HandledStamp('result', 'DummyHandler::__invoke')]);
        $bus->expects(self::once())->method('dispatch')->willReturn($envelope);

        self::assertSame('result', $queryBus->query($envelope));
    }

    public function testHandleThrowsOnNoHandledStamp()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" was handled zero times. Exactly one handler is expected when using "Symfony\Component\Messenger\Tests\TestQueryBus::handle()".');
        $bus = self::createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $query = new DummyMessage('Hello');
        $bus->expects(self::once())->method('dispatch')->willReturn(new Envelope($query));

        $queryBus->query($query);
    }

    public function testHandleThrowsOnMultipleHandledStamps()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Message of type "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" was handled multiple times. Only one handler is expected when using "Symfony\Component\Messenger\Tests\TestQueryBus::handle()", got 2: "FirstDummyHandler::__invoke", "SecondDummyHandler::__invoke".');
        $bus = self::createMock(MessageBus::class);
        $queryBus = new TestQueryBus($bus);

        $query = new DummyMessage('Hello');
        $bus->expects(self::once())->method('dispatch')->willReturn(
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
        if ($messageBus) {
            $this->messageBus = $messageBus;
        }
    }

    public function query($query): string
    {
        return $this->handle($query);
    }
}
