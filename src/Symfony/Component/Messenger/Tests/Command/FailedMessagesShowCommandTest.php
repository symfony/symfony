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
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
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
    private $colSize;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS='.(119 + \strlen(\PHP_EOL)));
    }

    protected function tearDown(): void
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
    }

    public function testBasicRunWithServiceLocator()
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

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            $failureTransportName,
            $serviceLocator
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

    public function testMultipleRedeliveryFailsWithServiceLocator()
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

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            $failureTransportName,
            $serviceLocator
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

    public function testReceiverShouldBeListableWithServiceLocator()
    {
        $receiver = $this->createMock(ReceiverInterface::class);
        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            $failureTransportName,
            $serviceLocator
        );

        $this->expectExceptionMessage('The "failure_receiver" receiver does not support listing or showing specific messages.');

        $tester = new CommandTester($command);
        $tester->execute(['id' => 15]);
    }

    public function testListMessagesWithServiceLocator()
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

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);
        $serviceLocator->method('getProvidedServices')->willReturn([
            $failureTransportName => [],
            'failure_receiver_2' => [],
            'failure_receiver_3' => [],
        ]);
        $command = new FailedMessagesShowCommand(
            $failureTransportName,
            $serviceLocator
        );
        $tester = new CommandTester($command);
        $tester->setInputs([0]);
        $tester->execute([]);

        $this->assertStringContainsString(sprintf(<<<EOF
15   stdClass   %s   Things are bad!
EOF
            ,
            $redeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s')),
            $tester->getDisplay(true));

        $expectedLoadingMessage = <<<EOF
> Available failure transports are: failure_receiver, failure_receiver_2, failure_receiver_3
EOF;

        $this->assertStringContainsString($expectedLoadingMessage, $tester->getDisplay());
        $this->assertStringContainsString('Run messenger:failed:show {id} --transport=failure_receiver -vv to see message details.', $tester->getDisplay());
    }

    public function testListMessagesReturnsNoMessagesFoundWithServiceLocator()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with()->willReturn([]);
        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            $failureTransportName,
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertStringContainsString('[OK] No failed messages were found.', $tester->getDisplay(true));
    }

    public function testListMessagesReturnsPaginatedMessagesWithServiceLocator()
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

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            $failureTransportName,
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute(['--max' => 1]);
        $this->assertStringContainsString('Showing first 1 messages.', $tester->getDisplay(true));
    }

    public function testListMessagesReturnsFilteredByClassMessage()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('async');
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
            new RedeliveryStamp(0),
            ErrorDetailsStamp::create(new \RuntimeException('Things are bad!')),
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->method('all')->with()->willReturn([$envelope]);

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand('failure_receiver', $serviceLocator);

        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertStringContainsString('Things are bad!', $tester->getDisplay(true));
        $tester->execute(['--class-filter' => 'stdClass']);
        $this->assertStringContainsString('Things are bad!', $tester->getDisplay(true));
        $this->assertStringContainsString('Showing 1 message(s).', $tester->getDisplay(true));
        $this->assertStringContainsString('Displaying only \'stdClass\' messages', $tester->getDisplay(true));

        $tester->execute(['--class-filter' => 'namespace\otherClass']);
        $this->assertStringContainsString('[OK] No failed messages were found.', $tester->getDisplay(true));
        $this->assertStringContainsString('Displaying only \'namespace\otherClass\' messages', $tester->getDisplay(true));
    }

    public function testListMessagesReturnsCountByClassName()
    {
        $sentToFailureStamp = new SentToFailureTransportStamp('async');
        $envelope = new Envelope(new \stdClass(), [
            new TransportMessageIdStamp(15),
            $sentToFailureStamp,
            new RedeliveryStamp(0),
            ErrorDetailsStamp::create(new \RuntimeException('Things are bad!')),
        ]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->method('all')->with()->willReturn([$envelope, $envelope]);

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand('failure_receiver', $serviceLocator);

        $tester = new CommandTester($command);
        $tester->execute(['--stats' => 1]);
        $this->assertStringContainsString('stdClass   2', $tester->getDisplay(true));
    }

    public function testInvalidMessagesThrowsExceptionWithServiceLocator()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            $failureTransportName,
            $serviceLocator
        );

        $this->expectExceptionMessage('The message "15" was not found.');

        $tester = new CommandTester($command);
        $tester->execute(['id' => 15]);
    }

    public function testVeryVerboseOutputForSingleMessageContainsExceptionWithTraceWithServiceLocator()
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

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand($failureTransportName, $serviceLocator);
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
      Symfony\Component\Messenger\Tests\Command\FailedMessagesShowCommandTest->testVeryVerboseOutputForSingleMessageContainsExceptionWithTraceWithServiceLocator()
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

    public function testListMessagesWithServiceLocatorFromSpecificTransport()
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

        $failureTransportName = 'failure_receiver_another';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            'global_but_not_used',
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute(['--transport' => $failureTransportName]);
        $this->assertStringContainsString(sprintf(<<<EOF
15   stdClass   %s   Things are bad!
EOF
            ,
            $redeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s')),
            $tester->getDisplay(true));
    }


    public function testCompletingTransport()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $receiver = $this->createMock(ListableReceiverInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('getProvidedServices')->willReturn([
            'global_receiver' => $receiver,
            $globalFailureReceiverName => $receiver,
        ]);

        $command = new FailedMessagesShowCommand(
            $globalFailureReceiverName,
            $serviceLocator
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport']);
        $this->assertSame(['global_receiver', 'failure_receiver'], $suggestions);
    }

    public function testCompleteId()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(50)->willReturn([
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('2ab50dfa1fbf')]),
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('78c2da843723')]),
        ]);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->with($globalFailureReceiverName)->willReturn(true);
        $serviceLocator->expects($this->any())->method('get')->with($globalFailureReceiverName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            $globalFailureReceiverName,
            $serviceLocator
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['']);

        $this->assertSame(['2ab50dfa1fbf', '78c2da843723'], $suggestions);
    }

    public function testCompleteIdWithSpecifiedTransport()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $anotherFailureReceiverName = 'another_receiver';

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(50)->willReturn([
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('2ab50dfa1fbf')]),
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('78c2da843723')]),
        ]);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->with($anotherFailureReceiverName)->willReturn(true);
        $serviceLocator->expects($this->any())->method('get')->with($anotherFailureReceiverName)->willReturn($receiver);

        $command = new FailedMessagesShowCommand(
            $globalFailureReceiverName,
            $serviceLocator
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport', $anotherFailureReceiverName, ' ']);

        $this->assertSame(['2ab50dfa1fbf', '78c2da843723'], $suggestions);
    }
}
