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
    /**
     * @group legacy
     */
    public function testBasicRun()
    {
        $receiver = self::createMock(ListableReceiverInterface::class);
        $receiver->expects(self::exactly(2))->method('find')->withConsecutive([10], [12])->willReturn(new Envelope(new \stdClass()));
        // message will eventually be ack'ed in Worker
        $receiver->expects(self::exactly(2))->method('ack');

        $dispatcher = new EventDispatcher();
        $bus = self::createMock(MessageBusInterface::class);
        // the bus should be called in the worker
        $bus->expects(self::exactly(2))->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            $receiver,
            $bus,
            $dispatcher
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [10, 12], '--force' => true]);

        self::assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testBasicRunWithServiceLocator()
    {
        $receiver = self::createMock(ListableReceiverInterface::class);
        $receiver->expects(self::exactly(2))->method('find')->withConsecutive([10], [12])->willReturn(new Envelope(new \stdClass()));
        // message will eventually be ack'ed in Worker
        $receiver->expects(self::exactly(2))->method('ack');

        $dispatcher = new EventDispatcher();
        $bus = self::createMock(MessageBusInterface::class);
        // the bus should be called in the worker
        $bus->expects(self::exactly(2))->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $failureTransportName = 'failure_receiver';
        $serviceLocator = self::createMock(ServiceLocator::class);
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

        self::assertStringContainsString('[OK]', $tester->getDisplay());
        self::assertStringNotContainsString('Available failure transports are:', $tester->getDisplay());
    }

    public function testBasicRunWithServiceLocatorMultipleFailedTransportsDefined()
    {
        $receiver = self::createMock(ListableReceiverInterface::class);
        $receiver->method('all')->willReturn([]);

        $dispatcher = new EventDispatcher();
        $bus = self::createMock(MessageBusInterface::class);

        $failureTransportName = 'failure_receiver';
        $serviceLocator = self::createMock(ServiceLocator::class);
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
        self::assertStringContainsString($expectedLadingMessage, $tester->getDisplay());
    }

    public function testBasicRunWithServiceLocatorWithSpecificFailureTransport()
    {
        $receiver = self::createMock(ListableReceiverInterface::class);
        $receiver->expects(self::exactly(2))->method('find')->withConsecutive([10], [12])->willReturn(new Envelope(new \stdClass()));
        // message will eventually be ack'ed in Worker
        $receiver->expects(self::exactly(2))->method('ack');

        $dispatcher = new EventDispatcher();
        $bus = self::createMock(MessageBusInterface::class);
        // the bus should be called in the worker
        $bus->expects(self::exactly(2))->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $failureTransportName = 'failure_receiver';
        $serviceLocator = self::createMock(ServiceLocator::class);
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

        self::assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testCompletingTransport()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $receiver = self::createMock(ListableReceiverInterface::class);

        $serviceLocator = self::createMock(ServiceLocator::class);
        $serviceLocator->expects(self::once())->method('getProvidedServices')->willReturn([
            'global_receiver' => $receiver,
            $globalFailureReceiverName => $receiver,
        ]);

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            $serviceLocator,
            self::createMock(MessageBusInterface::class),
            new EventDispatcher()
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport']);
        self::assertSame(['global_receiver', 'failure_receiver'], $suggestions);
    }

    public function testCompleteId()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $receiver = self::createMock(ListableReceiverInterface::class);
        $receiver->expects(self::once())->method('all')->with(50)->willReturn([
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('2ab50dfa1fbf')]),
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('78c2da843723')]),
        ]);

        $serviceLocator = self::createMock(ServiceLocator::class);
        $serviceLocator->expects(self::once())->method('has')->with($globalFailureReceiverName)->willReturn(true);
        $serviceLocator->expects(self::any())->method('get')->with($globalFailureReceiverName)->willReturn($receiver);

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            $serviceLocator,
            self::createMock(MessageBusInterface::class),
            new EventDispatcher()
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['']);

        self::assertSame(['2ab50dfa1fbf', '78c2da843723'], $suggestions);
    }

    public function testCompleteIdWithSpecifiedTransport()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $anotherFailureReceiverName = 'another_receiver';

        $receiver = self::createMock(ListableReceiverInterface::class);
        $receiver->expects(self::once())->method('all')->with(50)->willReturn([
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('2ab50dfa1fbf')]),
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('78c2da843723')]),
        ]);

        $serviceLocator = self::createMock(ServiceLocator::class);
        $serviceLocator->expects(self::once())->method('has')->with($anotherFailureReceiverName)->willReturn(true);
        $serviceLocator->expects(self::any())->method('get')->with($anotherFailureReceiverName)->willReturn($receiver);

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            $serviceLocator,
            self::createMock(MessageBusInterface::class),
            new EventDispatcher()
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport', $anotherFailureReceiverName, ' ']);

        self::assertSame(['2ab50dfa1fbf', '78c2da843723'], $suggestions);
    }
}
