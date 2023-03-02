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
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\HandlerArgumentsStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
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

    public static function itAddsHandledStampsProvider(): iterable
    {
        $first = new class() extends HandleMessageMiddlewareTestCallable {
            public function __invoke()
            {
                return 'first result';
            }
        };
        $firstClass = $first::class;

        $second = new class() extends HandleMessageMiddlewareTestCallable {
            public function __invoke()
            {
                return null;
            }
        };
        $secondClass = $second::class;

        $failing = new class() extends HandleMessageMiddlewareTestCallable {
            public function __invoke()
            {
                throw new \Exception('handler failed.');
            }
        };

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
        $this->expectException(NoHandlerForMessageException::class);
        $this->expectExceptionMessage('No handler for message "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage"');
        $middleware = new HandleMessageMiddleware(new HandlersLocator([]));

        $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware());
    }

    public function testMessageAlreadyHandled()
    {
        $handler = $this->createPartialMock(HandleMessageMiddlewareTestCallable::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        $envelope = new Envelope(new DummyMessage('Hey'));

        $envelope = $middleware->handle($envelope, $this->getStackMock());
        $handledStamp = $envelope->all(HandledStamp::class);

        $envelope = $middleware->handle($envelope, $this->getStackMock());

        $this->assertSame($envelope->all(HandledStamp::class), $handledStamp);
    }

    public function testAllowNoHandlers()
    {
        $middleware = new HandleMessageMiddleware(new HandlersLocator([]), true);

        $this->assertInstanceOf(Envelope::class, $middleware->handle(new Envelope(new DummyMessage('Hey')), new StackMiddleware()));
    }

    public function testBatchHandler()
    {
        $handler = new class() implements BatchHandlerInterface {
            public $processedMessages;

            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function getBatchSize(): int
            {
                return 2;
            }

            private function process(array $jobs): void
            {
                $this->processedMessages = array_column($jobs, 0);

                foreach ($jobs as [$job, $ack]) {
                    $ack->ack($job);
                }
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $ackedMessages = [];
        $ack = static function (Envelope $envelope, \Throwable $e = null) use (&$ackedMessages) {
            if (null !== $e) {
                throw $e;
            }
            $ackedMessages[] = $envelope->last(HandledStamp::class)->getResult();
        };

        $expectedMessages = [
            new DummyMessage('Hey'),
            new DummyMessage('Bob'),
        ];

        $envelopes = [];
        foreach ($expectedMessages as $message) {
            $envelopes[] = $middleware->handle(new Envelope($message, [new AckStamp($ack)]), new StackMiddleware());
        }

        $this->assertSame($expectedMessages, $handler->processedMessages);
        $this->assertSame($expectedMessages, $ackedMessages);

        $this->assertNotNull($envelopes[0]->last(NoAutoAckStamp::class));
        $this->assertNull($envelopes[1]->last(NoAutoAckStamp::class));
    }

    public function testBatchHandlerNoAck()
    {
        $handler = new class() implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function shouldFlush()
            {
                return true;
            }

            private function process(array $jobs): void
            {
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $error = null;
        $ack = static function (Envelope $envelope, \Throwable $e = null) use (&$error) {
            $error = $e;
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The acknowledger was not called by the "Symfony\Component\Messenger\Handler\BatchHandlerInterface@anonymous" batch handler.');

        $middleware->handle(new Envelope(new DummyMessage('Hey'), [new AckStamp($ack)]), new StackMiddleware());
    }

    public function testBatchHandlerNoBatch()
    {
        $handler = new class() implements BatchHandlerInterface {
            public $processedMessages;

            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, Acknowledger $ack = null)
            {
                return $this->handle($message, $ack);
            }

            private function shouldFlush()
            {
                return false;
            }

            private function process(array $jobs): void
            {
                $this->processedMessages = array_column($jobs, 0);
                [$job, $ack] = array_shift($jobs);
                $ack->ack($job);
            }
        };

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [new HandlerDescriptor($handler)],
        ]));

        $message = new DummyMessage('Hey');
        $middleware->handle(new Envelope($message), new StackMiddleware());

        $this->assertSame([$message], $handler->processedMessages);
    }

    public function testHandlerArgumentsStamp()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $envelope = $envelope->with(new HandlerArgumentsStamp(['additional argument']));

        $handler = $this->createPartialMock(HandleMessageMiddlewareTestCallable::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        $handler->expects($this->once())->method('__invoke')->with($message, 'additional argument');

        $middleware->handle($envelope, $this->getStackMock());
    }

    public function testHandlerArgumentsStampNamedArgument()
    {
        $message = new DummyMessage('Hey');
        $envelope = new Envelope($message);
        $envelope = $envelope->with(new HandlerArgumentsStamp(['namedArgument' => 'additional named argument']));

        $handler = $this->createPartialMock(HandleMessageMiddlewareNamedArgumentTestCallable::class, ['__invoke']);

        $middleware = new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [$handler],
        ]));

        $handler->expects($this->once())->method('__invoke')->with($message, 'additional named argument');

        $middleware->handle($envelope, $this->getStackMock());
    }
}

class HandleMessageMiddlewareTestCallable
{
    public function __invoke()
    {
    }
}

class HandleMessageMiddlewareNamedArgumentTestCallable
{
    public function __invoke(object $message, $namedArgument)
    {
    }
}
