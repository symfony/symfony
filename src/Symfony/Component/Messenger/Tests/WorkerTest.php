<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Asynchronous\Transport\ReceivedMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Tests\Fixtures\CallbackReceiver;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Worker;

class WorkerTest extends TestCase
{
    public function testWorkerDispatchTheReceivedMessage()
    {
        $apiMessage = new DummyMessage('API');
        $ipaMessage = new DummyMessage('IPA');

        $receiver = new CallbackReceiver(function ($handler) use ($apiMessage, $ipaMessage) {
            $handler(Envelope::wrap($apiMessage));
            $handler(Envelope::wrap($ipaMessage));
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $bus->expects($this->at(0))->method('dispatch')->with(Envelope::wrap($apiMessage)->with(new ReceivedMessage()));
        $bus->expects($this->at(1))->method('dispatch')->with(Envelope::wrap($ipaMessage)->with(new ReceivedMessage()));

        $worker = new Worker($receiver, $bus);
        $worker->run();
    }

    public function testWorkerDoesNotWrapMessagesAlreadyWrappedWithReceivedMessage()
    {
        $envelop = Envelope::wrap(new DummyMessage('API'))->with(new ReceivedMessage());
        $receiver = new CallbackReceiver(function ($handler) use ($envelop) {
            $handler($envelop);
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $bus->expects($this->at(0))->method('dispatch')->with($envelop);

        $worker = new Worker($receiver, $bus);
        $worker->run();
    }

    public function testWorkerIsThrowingExceptionsBackToGenerators()
    {
        $receiver = new CallbackReceiver(function ($handler) {
            try {
                $handler(Envelope::wrap(new DummyMessage('Hello')));

                $this->assertTrue(false, 'This should not be called because the exception is sent back to the generator.');
            } catch (\InvalidArgumentException $e) {
                // This should be called because of the exception sent back to the generator.
                $this->assertTrue(true);
            }
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->method('dispatch')->willThrowException(new \InvalidArgumentException('Why not'));

        $worker = new Worker($receiver, $bus);
        $worker->run();
    }

    public function testWorkerDoesNotSendNullMessagesToTheBus()
    {
        $receiver = new CallbackReceiver(function ($handler) {
            $handler(null);
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();
        $bus->expects($this->never())->method('dispatch');

        $worker = new Worker($receiver, $bus);
        $worker->run();
    }
}
