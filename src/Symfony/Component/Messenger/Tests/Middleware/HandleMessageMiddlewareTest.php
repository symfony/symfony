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
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
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

        $handler = $this->createPartialMock(HandleMessageMiddlewareTestCallable::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        $handler->expects($this->once())->method('__invoke')->with($message);

        $middleware->handle($envelope, $this->getStackMock());
    }

    /**
     * @dataProvider itAddsHandledStampsProvider
     */
    public function testItAddsHandledStamps(array $handlers, array $expectedStamps, bool $nextIsCalled)
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => $handlers,
        ]));

        try {
            $envelope = $middleware->handle($envelope, $this->getStackMock($nextIsCalled));
        } catch (HandlerFailedException $e) {
            $envelope = $e->getEnvelope();
        }

        $this->assertEquals($expectedStamps, $envelope->all(HandledStamp::class));
    }

    public function itAddsHandledStampsProvider(): iterable
    {
        $first = $this->createPartialMock(HandleMessageMiddlewareTestCallable::class, ['__invoke']);
        $first->method('__invoke')->willReturn('first result');
        $firstClass = \get_class($first);

        $second = $this->createPartialMock(HandleMessageMiddlewareTestCallable::class, ['__invoke']);
        $second->method('__invoke')->willReturn(null);
        $secondClass = \get_class($second);

        $failing = $this->createPartialMock(HandleMessageMiddlewareTestCallable::class, ['__invoke']);
        $failing->method('__invoke')->will($this->throwException(new \Exception('handler failed.')));

        yield 'A stamp is added' => [
            [$first],
            [new HandledStamp('first result', $firstClass.'::__invoke')],
            true,
        ];

        yield 'A stamp is added per handler' => [
            [
                new HandlerDescriptor($first, ['alias' => 'first']),
                new HandlerDescriptor($second, ['alias' => 'second']),
            ],
            [
                new HandledStamp('first result', $firstClass.'::__invoke@first'),
                new HandledStamp(null, $secondClass.'::__invoke@second'),
            ],
            true,
        ];

        yield 'It tries all handlers' => [
            [
                new HandlerDescriptor($first, ['alias' => 'first']),
                new HandlerDescriptor($failing, ['alias' => 'failing']),
                new HandlerDescriptor($second, ['alias' => 'second']),
            ],
            [
                new HandledStamp('first result', $firstClass.'::__invoke@first'),
                new HandledStamp(null, $secondClass.'::__invoke@second'),
            ],
            false,
        ];

        yield 'It ignores duplicated handler' => [
            [$first, $first],
            [
                new HandledStamp('first result', $firstClass.'::__invoke'),
            ],
            true,
        ];
    }

    public function testThrowsNoHandlerException()
    {
        $this->expectException('Symfony\Component\Messenger\Exception\NoHandlerForMessageException');
        $this->expectExceptionMessage('No handler for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"');
        $middleware = new HandleMessageMiddleware(new HandlersLocator([]));

        $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware());
    }

    public function testAllowNoHandlers()
    {
        $middleware = new HandleMessageMiddleware(new HandlersLocator([]), true);

        $this->assertInstanceOf(Envelope::class, $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware()));
    }
}

class HandleMessageMiddlewareTestCallable
{
    public function __invoke()
    {
    }
}
