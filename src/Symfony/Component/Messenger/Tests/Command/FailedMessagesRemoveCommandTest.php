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
    public function testBasicRun()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(20)->willReturn(new Envelope(new \stdClass()));

        $command = new FailedMessagesRemoveCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => 20, '--force' => true]);

        $this->assertStringContainsString('Message removed.', $tester->getDisplay());
    }
}
