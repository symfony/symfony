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
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

class FailedMessagesRemoveCommandTest extends TestCase
{
    public function testRemoveSingleMessageWithServiceLocator()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(20)->willReturn(new Envelope(new \stdClass()));
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->with($globalFailureReceiverName)->willReturn(true);
        $serviceLocator->expects($this->any())->method('get')->with($globalFailureReceiverName)->willReturn($receiver);

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => 20, '--force' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testRemoveUniqueMessageWithServiceLocator()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(20)->willReturn(new Envelope(new \stdClass()));
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->with($globalFailureReceiverName)->willReturn(true);
        $serviceLocator->expects($this->any())->method('get')->with($globalFailureReceiverName)->willReturn($receiver);

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20], '--force' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testRemoveUniqueMessageWithServiceLocatorFromSpecificFailureTransport()
    {
        $failureReveiverName = 'specific_failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(20)->willReturn(new Envelope(new \stdClass()));
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->with($failureReveiverName)->willReturn(true);
        $serviceLocator->expects($this->any())->method('get')->with($failureReveiverName)->willReturn($receiver);

        $command = new FailedMessagesRemoveCommand(
            $failureReveiverName,
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20], '--transport' => $failureReveiverName, '--force' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testThrowExceptionIfFailureTransportNotDefinedWithServiceLocator()
    {
        $failureReceiverName = 'failure_receiver';

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->with($failureReceiverName)->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $command = new FailedMessagesRemoveCommand(
            $failureReceiverName,
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20], '--transport' => $failureReceiverName, '--force' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testRemoveMultipleMessagesWithServiceLocator()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $series = [
            [[20], new Envelope(new \stdClass())],
            [[30], null],
            [[40], new Envelope(new \stdClass())],
        ];

        $receiver->expects($this->exactly(3))->method('find')
            ->willReturnCallback(function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->with($globalFailureReceiverName)->willReturn(true);
        $serviceLocator->expects($this->any())->method('get')->with($globalFailureReceiverName)->willReturn($receiver);

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20, 30, 40], '--force' => true]);

        $this->assertStringNotContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
        $this->assertStringContainsString('The message with id "30" was not found.', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 40 removed.', $tester->getDisplay());
    }

    public function testRemoveMultipleMessagesAndDisplayMessagesWithServiceLocator()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $series = [
            [[20], new Envelope(new \stdClass())],
            [[30], new Envelope(new \stdClass())],
        ];

        $receiver->expects($this->exactly(2))->method('find')
            ->willReturnCallback(function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator->expects($this->once())->method('has')->with($globalFailureReceiverName)->willReturn(true);
        $serviceLocator->expects($this->any())->method('get')->with($globalFailureReceiverName)->willReturn($receiver);

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            $serviceLocator
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20, 30], '--force' => true, '--show-messages' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 30 removed.', $tester->getDisplay());
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

        $command = new FailedMessagesRemoveCommand(
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

        $command = new FailedMessagesRemoveCommand(
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

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            $serviceLocator
        );

        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport', $anotherFailureReceiverName, ' ']);

        $this->assertSame(['2ab50dfa1fbf', '78c2da843723'], $suggestions);
    }
}
