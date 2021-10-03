<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MultipleHandlersForMessageException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\ExactlyOneHandlerLocator;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

final class ExactlyOneHandlerLocatorTest extends TestCase
{
    public function testItIsAHandlersLocator()
    {
        $this->assertInstanceOf(HandlersLocatorInterface::class, new ExactlyOneHandlerLocator([]));
    }

    public function testItYieldsExactlyOneHandler()
    {
        $handler = $this->createPartialMock(HandlersLocatorTestCallable::class, ['__invoke']);
        $locator = new ExactlyOneHandlerLocator([
            DummyMessage::class => [$handler],
        ]);

        $this->assertEquals([new HandlerDescriptor($handler)], iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a')))));
    }

    public function testItThrowsExceptionWhenNoHandlerIsDefined()
    {
        $this->expectException(NoHandlerForMessageException::class);

        $locator = new ExactlyOneHandlerLocator([DummyMessage::class => []]);

        iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a'))));
    }

    public function testItThrowsExceptionWhenMultipleHandlersAreDefined()
    {
        $this->expectException(MultipleHandlersForMessageException::class);

        $handler = $this->createPartialMock(HandlersLocatorTestCallable::class, ['__invoke']);
        $locator = new ExactlyOneHandlerLocator([
            DummyMessage::class => [
                new HandlerDescriptor($handler, ['alias' => 'one']),
                new HandlerDescriptor($handler, ['alias' => 'two']),
            ],
        ]);

        iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a'))));
    }
}
