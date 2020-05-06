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
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

class FailedMessagesRemoveCommandTest extends TestCase
{
    public function testRemoveSingleMessage()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(20)->willReturn(new Envelope(new \stdClass()));

        $command = new FailedMessagesRemoveCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => 20, '--force' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testRemoveUniqueMessage()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(20)->willReturn(new Envelope(new \stdClass()));

        $command = new FailedMessagesRemoveCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20], '--force' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testRemoveMultipleMessages()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->exactly(3))->method('find')->withConsecutive([20], [30], [40])->willReturnOnConsecutiveCalls(
            new Envelope(new \stdClass()),
            null,
            new Envelope(new \stdClass())
        );

        $command = new FailedMessagesRemoveCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20, 30, 40], '--force' => true]);

        $this->assertStringNotContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
        $this->assertStringContainsString('The message with id "30" was not found.', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 40 removed.', $tester->getDisplay());
    }

    public function testRemoveMultipleMessagesAndDisplayMessages()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->exactly(2))->method('find')->withConsecutive([20], [30])->willReturnOnConsecutiveCalls(
            new Envelope(new \stdClass()),
            new Envelope(new \stdClass())
        );

        $command = new FailedMessagesRemoveCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20, 30], '--force' => true, '--show-messages' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 30 removed.', $tester->getDisplay());
    }
}
