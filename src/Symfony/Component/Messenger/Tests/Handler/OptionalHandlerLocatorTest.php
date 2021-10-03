<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Handler\OptionalHandlerLocator;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

final class OptionalHandlerLocatorTest extends TestCase
{
    public function testItIsAHandlersLocator()
    {
        $this->assertInstanceOf(HandlersLocatorInterface::class, new OptionalHandlerLocator([]));
    }

    public function testItYieldsHandlers()
    {
        $handler = $this->createPartialMock(HandlersLocatorTestCallable::class, ['__invoke']);
        $locator = new OptionalHandlerLocator([
            DummyMessage::class => [
                $first = new HandlerDescriptor($handler, ['alias' => 'one']),
                $second = new HandlerDescriptor($handler, ['alias' => 'two']),
            ],
        ]);

        $this->assertSame([$first, $second], iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a')))));
    }

    public function testItYieldsNothingWhenNoHandlerIsLocated()
    {
        $locator = new OptionalHandlerLocator([DummyMessage::class => []]);

        $this->assertEmpty(iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a')))));
    }
}
