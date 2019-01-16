<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandleMessageMiddlewareTest extends MiddlewareTestCase
{
    public function testItCallsTheHandlerAndNextMiddleware()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $handler = $this->createPartialMock(\stdClass::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        $handler->expects($this->once())->method('__invoke')->with($message);

        $middleware->handle($envelope, $this->getStackMock());
    }

    /**
     * @dataProvider itAddsHandledStampsProvider
     */
    public function testItAddsHandledStamps(array $handlers, array $expectedStamps)
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => $handlers,
        ]));

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertEquals($expectedStamps, $envelope->all(HandledStamp::class));
    }

    public function itAddsHandledStampsProvider()
    {
        $first = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $first->method('__invoke')->willReturn('first result');
        $firstClass = \get_class($first);

        $second = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $second->method('__invoke')->willReturn(null);
        $secondClass = \get_class($second);

        yield 'A stamp is added' => [
            [$first],
            [new HandledStamp('first result', $firstClass.'::__invoke')],
        ];

        yield 'A stamp is added per handler' => [
            [$first, $second],
            [
                new HandledStamp('first result', $firstClass.'::__invoke'),
                new HandledStamp(null, $secondClass.'::__invoke'),
            ],
        ];

        yield 'Yielded locator alias is used' => [
            ['first_alias' => $first, $second],
            [
                new HandledStamp('first result', $firstClass.'::__invoke', 'first_alias'),
                new HandledStamp(null, $secondClass.'::__invoke'),
            ],
        ];
    }

    /**
     * @expectedException \Symfony\Component\Messenger\Exception\NoHandlerForMessageException
     * @expectedExceptionMessage No handler for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"
     */
    public function testThrowsNoHandlerException()
    {
        $middleware = new HandleMessageMiddleware(new HandlersLocator([]));

        $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware());
    }

    public function testAllowNoHandlers()
    {
        $middleware = new HandleMessageMiddleware(new HandlersLocator([]), true);

        $this->assertInstanceOf(Envelope::class, $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware()));
    }
}
