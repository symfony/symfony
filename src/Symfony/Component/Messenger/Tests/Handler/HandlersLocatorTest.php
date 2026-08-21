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
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandlersLocatorTest extends TestCase
{
    public function testItYieldsHandlerDescriptors()
    {
        $handler = new HandlersLocatorTestCallable();
        $locator = new HandlersLocator([
            DummyMessage::class => [$handler],
        ]);

        $descriptor = new HandlerDescriptor($handler);

        $handlers = iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a'))));

        $this->assertCount(1, $handlers);
        $this->assertSame($descriptor->getName(), $handlers[0]->getName());
        $this->assertSame($handler, (new \ReflectionFunction($handlers[0]->getHandler()))->getClosureThis());
    }

    public function testItYieldsHandlersHavingTheSameNameOnlyOnce()
    {
        $locator = new HandlersLocator([
            DummyMessage::class => [
                $first = new HandlerDescriptor(new HandlersLocatorTestCallable()),
                new HandlerDescriptor(new HandlersLocatorTestCallable()),
            ],
        ]);

        $this->assertSame([$first], iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('Body')))));
    }

    public function testItReturnsOnlyHandlersMatchingTransport()
    {
        $firstHandler = new HandlersLocatorTestCallable();
        $secondHandler = new HandlersLocatorTestCallable();

        $locator = new HandlersLocator([
            DummyMessage::class => [
                $first = new HandlerDescriptor($firstHandler, ['alias' => 'one']),
                new HandlerDescriptor(new HandlersLocatorTestCallable(), ['from_transport' => 'ignored', 'alias' => 'two']),
                $second = new HandlerDescriptor($secondHandler, ['from_transport' => 'transportName', 'alias' => 'three']),
            ],
        ]);

        $first->getName();
        $second->getName();

        $this->assertEquals([
            $first,
            $second,
        ], iterator_to_array($locator->getHandlers(
            new Envelope(new DummyMessage('Body'), [new ReceivedStamp('transportName')])
        )));
    }

    public function testItReturnsOnlyHandlersMatchingMessageNamespace()
    {
        $firstHandler = new HandlersLocatorTestCallable();
        $secondHandler = new HandlersLocatorTestCallable();

        $locator = new HandlersLocator([
            str_replace('DummyMessage', '*', DummyMessage::class) => [
                $first = new HandlerDescriptor($firstHandler, ['alias' => 'one']),
            ],
            str_replace('Fixtures\\DummyMessage', '*', DummyMessage::class) => [
                $second = new HandlerDescriptor($secondHandler, ['alias' => 'two']),
            ],
        ]);

        $first->getName();
        $second->getName();

        $this->assertEquals([
            $first,
            $second,
        ], iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('Body')))));
    }
}

class HandlersLocatorTestCallable
{
    public function __invoke()
    {
    }
}
