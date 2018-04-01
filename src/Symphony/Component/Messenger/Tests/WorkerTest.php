<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Messenger\Asynchronous\Transport\ReceivedMessage;
use Symphony\Component\Messenger\MessageBusInterface;
use Symphony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symphony\Component\Messenger\Transport\ReceiverInterface;
use Symphony\Component\Messenger\Worker;

class WorkerTest extends TestCase
{
    public function testWorkerDispatchTheReceivedMessage()
    {
        $receiver = new CallbackReceiver(function() {
            yield new DummyMessage('API');
            yield new DummyMessage('IPA');
        });

        $bus = $this->createMock(MessageBusInterface::class);

        $bus->expects($this->at(0))->method('dispatch')->with(new ReceivedMessage(new DummyMessage('API')));
        $bus->expects($this->at(1))->method('dispatch')->with(new ReceivedMessage(new DummyMessage('IPA')));

        $worker = new Worker($receiver, $bus);
        $worker->run();
    }

    public function testWorkerDoesNotWrapMessagesAlreadyWrappedInReceivedMessages()
    {
        $receiver = new CallbackReceiver(function() {
            yield new ReceivedMessage(new DummyMessage('API'));
        });

        $bus = $this->createMock(MessageBusInterface::class);

        $bus->expects($this->at(0))->method('dispatch')->with(new ReceivedMessage(new DummyMessage('API')));

        $worker = new Worker($receiver, $bus);
        $worker->run();
    }

    public function testWorkerIsThrowingExceptionsBackToGenerators()
    {
        $receiver = new CallbackReceiver(function() {
            try {
                yield new DummyMessage('Hello');

                $this->assertTrue(false, 'This should not be called because the exception is sent back to the generator.');
            } catch (\InvalidArgumentException $e) {
                // This should be called because of the exception sent back to the generator.
                $this->assertTrue(true);
            }
        });

        $bus = $this->createMock(MessageBusInterface::class);

        $bus->method('dispatch')->willThrowException(new \InvalidArgumentException('Why not'));

        $worker = new Worker($receiver, $bus);
        $worker->run();
    }
}

class CallbackReceiver implements ReceiverInterface
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function receive(): iterable
    {
        $callable = $this->callable;

        return $callable();
    }
}
