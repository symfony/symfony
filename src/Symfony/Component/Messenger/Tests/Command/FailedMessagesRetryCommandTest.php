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
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

class FailedMessagesRetryCommandTest extends TestCase
{
    public function testBasicRunWithServiceLocator()
    {
        $series = [
            [[10], new Envelope(new \stdClass())],
            [[12], new Envelope(new \stdClass())],
        ];

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->exactly(2))->method('find')
            ->willReturnCallback(function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        // message will eventually be ack'ed in Worker
        $receiver->expects($this->exactly(2))->method('ack');

        $dispatcher = new EventDispatcher();
        $bus = $this->createMock(MessageBusInterface::class);
        // the bus should be called in the worker
        $bus->expects($this->exactly(2))->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            $serviceLocator,
            $bus,
            $dispatcher
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [10, 12], '--force' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
        $this->assertStringNotContainsString('Available failure transports are:', $tester->getDisplay());
    }

    public function testBasicRunWithServiceLocatorMultipleFailedTransportsDefined()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->method('all')->willReturn([]);

        $dispatcher = new EventDispatcher();
        $bus = $this->createMock(MessageBusInterface::class);

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);
        $serviceLocator->method('getProvidedServices')->willReturn([
            'failure_receiver' => [],
            'failure_receiver_2' => [],
            'failure_receiver_3' => [],
        ]);

        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            $serviceLocator,
            $bus,
            $dispatcher
        );
        $tester = new CommandTester($command);
        $tester->setInputs([0]);
        $tester->execute(['--force' => true]);

        $expectedLadingMessage = <<<EOF
> Available failure transports are: failure_receiver, failure_receiver_2, failure_receiver_3
EOF;
        $this->assertStringContainsString($expectedLadingMessage, $tester->getDisplay());
    }

    public function testBasicRunWithServiceLocatorWithSpecificFailureTransport()
    {
        $series = [
            [[10], new Envelope(new \stdClass())],
            [[12], new Envelope(new \stdClass())],
        ];

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->exactly(2))->method('find')
            ->willReturnCallback(function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        // message will eventually be ack'ed in Worker
        $receiver->expects($this->exactly(2))->method('ack');

        $dispatcher = new EventDispatcher();
        $bus = $this->createMock(MessageBusInterface::class);
        // the bus should be called in the worker
        $bus->expects($this->exactly(2))->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $failureTransportName = 'failure_receiver';
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->method('has')->with($failureTransportName)->willReturn(true);
        $serviceLocator->method('get')->with($failureTransportName)->willReturn($receiver);

        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            $serviceLocator,
            $bus,
            $dispatcher
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [10, 12], '--transport' => $failureTransportName, '--force' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
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

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            $serviceLocator,
            $this->createMock(MessageBusInterface::class),
            new EventDispatcher()
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

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            $serviceLocator,
            $this->createMock(MessageBusInterface::class),
            new EventDispatcher()
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

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            $serviceLocator,
            $this->createMock(MessageBusInterface::class),
            new EventDispatcher()
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport', $anotherFailureReceiverName, ' ']);

        $this->assertSame(['2ab50dfa1fbf', '78c2da843723'], $suggestions);
    }
}
