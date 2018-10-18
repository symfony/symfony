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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
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
            $handler(new Envelope($apiMessage));
            $handler(new Envelope($ipaMessage));
        });

        $bus = $this->getMockBuilder(MessageBusInterface::class)->getMock();

        $bus->expects($this->at(0))->method('dispatch')->with((new Envelope($apiMessage))->with(new ReceivedStamp()));
        $bus->expects($this->at(1))->method('dispatch')->with((new Envelope($ipaMessage))->with(new ReceivedStamp()));

        $worker = new Worker($receiver, $bus);
        $worker->run();
    }

    public function testWorkerDoesNotWrapMessagesAlreadyWrappedWithReceivedMessage()
    {
        $envelop = (new Envelope(new DummyMessage('API')))->with(new ReceivedStamp());
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
                $handler(new Envelope(new DummyMessage('Hello')));

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
