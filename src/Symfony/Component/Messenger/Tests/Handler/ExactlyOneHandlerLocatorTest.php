<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Handler\ExactlyOneHandlerLocator;
use Symfony\Component\Messenger\Exception\MultipleHandlersForMessageException;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\SecondMessage;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

class ExactlyOneHandlerLocatorTest extends TestCase
{
    public function testItIsAHandlerLocator(): void
    {
        $this->assertInstanceOf(HandlersLocatorInterface::class, new ExactlyOneHandlerLocator([]));
    }

    public function testItProvidesAHandler(): void
    {
        $handler1 = $this->createPartialMock(ExactlyOneHandlerLocatorTestCallable::class, ['__invoke']);
        $handler2 = $this->createPartialMock(ExactlyOneHandlerLocatorTestCallable::class, ['__invoke']);

        $locator = new ExactlyOneHandlerLocator([
            DummyMessage::class => [$handler1],
            SecondMessage::class => [$handler2],
        ]);

        $this->assertSame([$handler1], iterator_to_array($locator->getHandlers(
            new Envelope(new DummyMessage('Body'), [new ReceivedStamp('transportName')])
        )));
    }

    public function testItThrowsAnExceptionWhenMultipleHandlersMatchesMessage()
    {
        $this->expectException(MultipleHandlersForMessageException::class);

        new ExactlyOneHandlerLocator([
            DummyMessage::class => [
                $this->createPartialMock(ExactlyOneHandlerLocatorTestCallable::class, ['__invoke']),
                $this->createPartialMock(ExactlyOneHandlerLocatorTestCallable::class, ['__invoke']),
            ],
        ]);
    }

    public function testItThrowsExceptionWhenNoHandlerMatchesMessage(): void
    {
        $this->expectException(NoHandlerForMessageException::class);

        $locator = new ExactlyOneHandlerLocator([]);
        iterator_to_array($locator->getHandlers(
            new Envelope(new DummyMessage('Body'), [new ReceivedStamp('transportName')])
        ));
    }
}

class ExactlyOneHandlerLocatorTestCallable
{
    public function __invoke()
    {
    }
}
