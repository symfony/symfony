<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Worker;

class FailedMessagesRetryCommandTest extends TestCase
{
    public function testBasicRun()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->exactly(2))->method('find')->withConsecutive([10], [12])->willReturn(new Envelope(new \stdClass()));
        // message will eventually be ack'ed in Worker
        $receiver->expects($this->exactly(2))->method('ack');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        // the bus should be called in the worker
        $bus->expects($this->exactly(2))->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            $receiver,
            $bus,
            $dispatcher
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [10, 12]]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }
}
