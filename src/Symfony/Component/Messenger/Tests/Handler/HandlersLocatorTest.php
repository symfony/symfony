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
        $handler = $this->createPartialMock(HandlersLocatorTestCallable::class, ['__invoke']);
        $locator = new HandlersLocator([
            DummyMessage::class => [$handler],
        ]);

        $this->assertEquals([new HandlerDescriptor($handler)], iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a')))));
    }

    public function testItReturnsOnlyHandlersMatchingTransport()
    {
        $firstHandler = $this->createPartialMock(HandlersLocatorTestCallable::class, ['__invoke']);
        $secondHandler = $this->createPartialMock(HandlersLocatorTestCallable::class, ['__invoke']);

        $locator = new HandlersLocator([
            DummyMessage::class => [
                $first = new HandlerDescriptor($firstHandler, ['alias' => 'one']),
                new HandlerDescriptor($this->createPartialMock(HandlersLocatorTestCallable::class, ['__invoke']), ['from_transport' => 'ignored', 'alias' => 'two']),
                $second = new HandlerDescriptor($secondHandler, ['from_transport' => 'transportName', 'alias' => 'three']),
            ],
        ]);

        $this->assertEquals([
            $first,
            $second,
        ], iterator_to_array($locator->getHandlers(
            new Envelope(new DummyMessage('Body'), [new ReceivedStamp('transportName')])
        )));
    }
}

class HandlersLocatorTestCallable
{
    public function __invoke()
    {
    }
}
