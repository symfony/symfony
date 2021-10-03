<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\AtLeastOneHandlerLocator;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

final class AtLeastOneHandlerLocatorTest extends TestCase
{
    public function testItIsAHandlersLocator()
    {
        $this->assertInstanceOf(HandlersLocatorInterface::class, new AtLeastOneHandlerLocator([]));
    }

    public function testItYieldsHandlers()
    {
        $handler = $this->createPartialMock(HandlersLocatorTestCallable::class, ['__invoke']);
        $locator = new AtLeastOneHandlerLocator([
            DummyMessage::class => [
                $first = new HandlerDescriptor($handler, ['alias' => 'one']),
                $second = new HandlerDescriptor($handler, ['alias' => 'two']),
            ],
        ]);

        $this->assertSame([$first, $second], iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a')))));
    }

    public function testItThrowsExceptionWhenNoHandlerIsDefined()
    {
        $this->expectException(NoHandlerForMessageException::class);

        $locator = new AtLeastOneHandlerLocator([DummyMessage::class => []]);

        iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a'))));
    }
}
