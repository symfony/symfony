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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\MessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\MessageRecorder;
use Symfony\Component\Messenger\MessageRecorderInterface;
use Symfony\Component\Messenger\Middleware\HandleRecordedMessageMiddleware;
use Symfony\Component\Messenger\RecordedMessageCollectionInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandleRecordedMessageMiddlewareTest extends TestCase
{
    public function testResetRecorderOnException()
    {
        $message = new DummyMessage('Hello');
        $messageBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $recorder = new MessageRecorder();
        $next = new class($recorder) {
            private $recorder;

            public function __construct(MessageRecorderInterface $recorder)
            {
                $this->recorder = $recorder;
            }

            public function __invoke()
            {
                $this->recorder->record(new \stdClass());
                throw new \LogicException();
            }
        };

        $middleware = new HandleRecordedMessageMiddleware($messageBus, $recorder);
        try {
            $middleware->handle($message, $next);
        } catch (\LogicException $e) {
        }

        $this->assertEmpty($recorder->getRecordedMessages());
    }

    public function testResetRecorderOnStart()
    {
        $message = new DummyMessage('Hello');
        $recorder = new MessageRecorder();
        $recorder->record(new \stdClass());
        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->expects($this->once())->method('__invoke')->willReturn('World');
        $messageBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $messageBus->expects($this->exactly(0))->method('dispatch');

        $middleware = new HandleRecordedMessageMiddleware($messageBus, $recorder);
        $middleware->handle($message, $next);
        $this->assertEmpty($recorder->getRecordedMessages());
    }

    public function testDispatchMessageToBus()
    {
        $message = new DummyMessage('Hello');
        $recorder = new MessageRecorder();
        $messageBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $messageBus->expects($this->exactly(3))->method('dispatch')->willReturnCallback(function ($a) use ($recorder) {
            if (2 === $a->idx) {
                $message = new \stdClass();
                $message->idx = 3;
                $recorder->record($message);
            }

            return;
        });

        $next = new class($recorder) {
            private $recorder;

            public function __construct(MessageRecorderInterface $recorder)
            {
                $this->recorder = $recorder;
            }

            public function __invoke()
            {
                $message = new \stdClass();
                $message->idx = 1;
                $this->recorder->record($message);

                $message = new \stdClass();
                $message->idx = 2;
                $this->recorder->record($message);
            }
        };

        $middleware = new HandleRecordedMessageMiddleware($messageBus, $recorder);
        $middleware->handle($message, $next);
        $this->assertEmpty($recorder->getRecordedMessages(), 'RecordedMessageContainerInterface should be empty after execution of HandleRecordedMessageMiddleware.');
    }

    public function testCatchExceptions()
    {
        $message = new DummyMessage('Hello');
        $recorder = new MessageRecorder();
        $messageBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $messageBus->expects($this->exactly(2))->method('dispatch')->willThrowException(new \LogicException('Foo'));

        $next = new class($recorder) {
            private $recorder;

            public function __construct(MessageRecorderInterface $recorder)
            {
                $this->recorder = $recorder;
            }

            public function __invoke()
            {
                $message = new \stdClass();
                $message->idx = 1;
                $this->recorder->record($message);

                $message = new \stdClass();
                $message->idx = 2;
                $this->recorder->record($message);
            }
        };

        $middleware = new HandleRecordedMessageMiddleware($messageBus, $recorder);
        $this->expectException(MessageHandlingException::class);
        $this->expectExceptionMessage('Some handlers for recorded messages threw an exception. Their messages were: 

Foo, 
Foo');
        $middleware->handle($message, $next);
    }

    public function testItReturnsData()
    {
        $message = new DummyMessage('Hello');

        $next = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $next->method('__invoke')->willReturn('World');

        $messageBus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $recorder = $this->getMockBuilder(RecordedMessageCollectionInterface::class)->getMock();

        $middleware = new HandleRecordedMessageMiddleware($messageBus, $recorder);

        $result = $middleware->handle($message, $next);

        $this->assertEquals('World', $result);
    }
}
