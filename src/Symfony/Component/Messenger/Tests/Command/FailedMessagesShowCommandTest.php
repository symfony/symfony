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
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * @group time-sensitive
 */
class FailedMessagesShowCommandTest extends TestCase
{
    public function testBasicRun()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('Things are bad!', 'async');
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(15)->willReturn($envelope);

        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => 15]);

        $this->assertContains(sprintf(<<<EOF
 ------------- --------------------- 
  Class         stdClass             
  Message Id    15                   
  Failed at     %s  
  Error         Things are bad!      
  Error Class   (unknown)
EOF
            ,
            $sentToFailureStamp->getSentAt()->format('Y-m-d H:i:s')),
            $tester->getDisplay(true));
    }
}
