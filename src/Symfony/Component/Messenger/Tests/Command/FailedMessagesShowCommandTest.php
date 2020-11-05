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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @group time-sensitive
 */
class FailedMessagesShowCommandTest extends TestCase
{
    public function testBasicRun()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('async');
        $redeliveryStamp = new RedeliveryStamp(0);
        $errorStamp = ErrorDetailsStamp::create(new \Exception('Things are bad!', 123));
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
            $redeliveryStamp,
            $errorStamp,
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(15)->willReturn($envelope);

        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => 15]);

        $this->assertStringContainsString(sprintf(<<<EOF
 ------------- --------------------- 
  Class         stdClass             
  Message Id    15                   
  Failed at     %s  
  Error         Things are bad!      
  Error Code    123                  
  Error Class   Exception            
  Transport     async                
EOF
            ,
            $redeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s')),
            $tester->getDisplay(true));
    }

    public function testMultipleRedeliveryFails()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('async');
        $redeliveryStamp1 = new RedeliveryStamp(0);
        $errorStamp = ErrorDetailsStamp::create(new \Exception('Things are bad!', 123));
        $redeliveryStamp2 = new RedeliveryStamp(0);
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
            $redeliveryStamp1,
            $errorStamp,
            $redeliveryStamp2,
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(15)->willReturn($envelope);
        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );
        $tester = new CommandTester($command);
        $tester->execute(['id' => 15]);
        $this->assertStringContainsString(sprintf(<<<EOF
 ------------- --------------------- 
  Class         stdClass             
  Message Id    15                   
  Failed at     %s  
  Error         Things are bad!      
  Error Code    123                  
  Error Class   Exception            
  Transport     async                
EOF
            ,
            $redeliveryStamp2->getRedeliveredAt()->format('Y-m-d H:i:s')),
            $tester->getDisplay(true));
    }

    /**
     * @group legacy
     */
    public function testLegacyFallback()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('async');
        $redeliveryStamp = new RedeliveryStamp(0, 'Things are bad!');
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
            $redeliveryStamp,
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(15)->willReturn($envelope);
        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );
        $tester = new CommandTester($command);
        $tester->execute(['id' => 15]);
        $this->assertStringContainsString(sprintf(<<<EOF
 ------------- --------------------- 
  Class         stdClass             
  Message Id    15                   
  Failed at     %s  
  Error         Things are bad!      
  Error Code                         
  Error Class   (unknown)            
  Transport     async                
EOF
            ,
            $redeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s')),
            $tester->getDisplay(true));
    }

    public function testReceiverShouldBeListable()
    {
        $receiver = $this->createMock(ReceiverInterface::class);
        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );

        $this->expectExceptionMessage('The "failure_receiver" receiver does not support listing or showing specific messages.');

        $tester = new CommandTester($command);
        $tester->execute(['id' => 15]);
    }

    public function testListMessages()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('async');
        $redeliveryStamp = new RedeliveryStamp(0);
        $errorStamp = ErrorDetailsStamp::create(new \RuntimeException('Things are bad!'));
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
            $redeliveryStamp,
            $errorStamp,
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with()->willReturn([$envelope]);

        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertStringContainsString(sprintf(<<<EOF
15   stdClass   %s   Things are bad!
EOF
            ,
            $redeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s')),
            $tester->getDisplay(true));
    }

    public function testListMessagesReturnsNoMessagesFound()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with()->willReturn([]);

        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertStringContainsString('[OK] No failed messages were found.', $tester->getDisplay(true));
    }

    public function testListMessagesReturnsPaginatedMessages()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('async');
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
            new RedeliveryStamp(0),
            ErrorDetailsStamp::create(new \RuntimeException('Things are bad!')),
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with()->willReturn([$envelope]);

        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );

        $tester = new CommandTester($command);
        $tester->execute(['--max' => 1]);
        $this->assertStringContainsString('Showing first 1 messages.', $tester->getDisplay(true));
    }

    public function testInvalidMessagesThrowsException()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('async');
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $command = new FailedMessagesShowCommand(
            'failure_receiver',
            $receiver
        );

        $this->expectExceptionMessage('The message "15" was not found.');

        $tester = new CommandTester($command);
        $tester->execute(['id' => 15]);
    }

    public function testVeryVerboseOutputForSingleMessageContainsExceptionWithTrace()
    {
        $exception = new \RuntimeException('Things are bad!');
        $exceptionLine = __LINE__ - 1;
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            new SentToFailureTransportStamp('async'),
            new RedeliveryStamp(0),
            ErrorDetailsStamp::create($exception),
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(42)->willReturn($envelope);

        $command = new FailedMessagesShowCommand('failure_receiver', $receiver);
        $tester = new CommandTester($command);
        $tester->execute(['id' => 42], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);
        $this->assertStringMatchesFormat(sprintf(<<<'EOF'
%%A
Exception:
==========

RuntimeException {
  message: "Things are bad!"
  code: 0
  file: "%s"
  line: %d
  trace: {
    %%s%%eTests%%eCommand%%eFailedMessagesShowCommandTest.php:%d {
      Symfony\Component\Messenger\Tests\Command\FailedMessagesShowCommandTest->testVeryVerboseOutputForSingleMessageContainsExceptionWithTrace()
      › {
      ›     $exception = new \RuntimeException('Things are bad!');
      ›     $exceptionLine = __LINE__ - 1;
    }
%%A
EOF
            ,
            __FILE__, $exceptionLine, $exceptionLine),
            $tester->getDisplay(true));
    }
}
