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
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceiver;
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

class FailedMessagesRemoveCommandTest extends TestCase
{
    public function testRemoveSingleMessageWithServiceLocator()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(20)->willReturn(new Envelope(new \stdClass()));

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver])
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

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver])
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

        $command = new FailedMessagesRemoveCommand(
            $failureReveiverName,
            new ServiceLocator([$failureReveiverName => static fn () => $receiver])
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20], '--transport' => $failureReveiverName, '--force' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testThrowExceptionIfFailureTransportNotDefinedWithServiceLocator()
    {
        $failureReceiverName = 'failure_receiver';

        $this->expectException(InvalidArgumentException::class);
        $command = new FailedMessagesRemoveCommand(
            $failureReceiverName,
            new ServiceLocator([])
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

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver])
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

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver])
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => [20, 30], '--force' => true, '--show-messages' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 30 removed.', $tester->getDisplay());
    }

    public function testRemoveMessagesFilteredByClassMessage()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $anotherClass = new class extends \stdClass {};

        $series = [
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(10)]),
            new Envelope(new $anotherClass(), [new TransportMessageIdStamp(20)]),
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(30)]),
        ];
        $receiver->expects($this->once())->method('all')->willReturn($series);

        $expectedRemovedIds = [10, 30];
        $receiver->expects($this->exactly(2))->method('find')
            ->willReturnCallback(function (...$args) use ($series, &$expectedRemovedIds) {
                $expectedArgs = array_shift($expectedRemovedIds);
                $this->assertSame([$expectedArgs], $args);

                $return = array_filter(
                    $series,
                    static fn (Envelope $envelope) => [$envelope->last(TransportMessageIdStamp::class)->getId()] === $args,
                );

                return current($return);
            })
        ;

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver])
        );

        $tester = new CommandTester($command);
        $tester->execute(['--class-filter' => 'stdClass', '--force' => true, '--show-messages' => true]);

        $this->assertStringContainsString('Can you confirm you want to remove 2 messages? (yes/no)', $tester->getDisplay());
        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 10 removed.', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 30 removed.', $tester->getDisplay());
    }

    public function testCompletingTransport()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $receiver = $this->createStub(ListableReceiverInterface::class);

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            new ServiceLocator([
                'global_receiver' => static fn () => $receiver,
                $globalFailureReceiverName => static fn () => $receiver,
            ])
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport']);
        $this->assertSame(['global_receiver', 'failure_receiver'], $suggestions);
    }

    public function testCompleteId()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->willReturn([
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('2ab50dfa1fbf')]),
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('78c2da843723')]),
        ]);

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver])
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
        $receiver->expects($this->once())->method('all')->willReturn([
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('2ab50dfa1fbf')]),
            Envelope::wrap(new \stdClass(), [new TransportMessageIdStamp('78c2da843723')]),
        ]);

        $command = new FailedMessagesRemoveCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$anotherFailureReceiverName => static fn () => $receiver])
        );

        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport', $anotherFailureReceiverName, ' ']);

        $this->assertSame(['2ab50dfa1fbf', '78c2da843723'], $suggestions);
    }

    public function testOptionAllIsSetWithIdsThrows()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $command = new FailedMessagesRemoveCommand('failure_receiver', new ServiceLocator([$globalFailureReceiverName => fn () => $this->createStub(ListableReceiverInterface::class)]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot specify message ids when using the "--all" option.');
        $tester->execute(['id' => [20], '--all' => true]);
    }

    public function testOptionAllIsSetWithoutForceAsksConfirmation()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $receiver = $this->createStub(ListableReceiverInterface::class);

        $command = new FailedMessagesRemoveCommand('failure_receiver', new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver]));
        $tester = new CommandTester($command);

        $tester->execute(['--all' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Do you want to permanently remove all failed messages? (yes/no)', $tester->getDisplay());
    }

    public function testOptionAllIsSetWithoutForceAsksConfirmationOnMessageCountAwareInterface()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $receiver = $this->createMock(DoctrineReceiver::class);
        $receiver->expects($this->once())->method('getMessageCount')->willReturn(2);

        $command = new FailedMessagesRemoveCommand('failure_receiver', new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver]));
        $tester = new CommandTester($command);

        $tester->execute(['--all' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Do you want to permanently remove all (2) messages? (yes/no)', $tester->getDisplay());
    }

    public function testOptionAllIsNotSetNorIdsThrows()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $command = new FailedMessagesRemoveCommand('failure_receiver', new ServiceLocator([$globalFailureReceiverName => fn () => $this->createStub(ListableReceiverInterface::class)]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please specify at least one message id. If you want to remove all failed messages, use the "--all" option.');
        $tester->execute([]);
    }

    public function testRemoveAllMessages()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $series = [
            new Envelope(new \stdClass()),
            new Envelope(new \stdClass()),
            new Envelope(new \stdClass()),
            new Envelope(new \stdClass()),
        ];

        $receiver->expects($this->once())->method('all')->willReturn($series);

        $command = new FailedMessagesRemoveCommand($globalFailureReceiverName, new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver]));
        $tester = new CommandTester($command);
        $tester->execute(['--all' => true, '--force' => true, '--show-messages' => true]);

        $this->assertStringContainsString('Failed Message Details', $tester->getDisplay());
        $this->assertStringContainsString('4 messages were removed.', $tester->getDisplay());
    }

    public function testSuccessMessageGoesToStdout()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $envelope = new Envelope(new \stdClass(), [new TransportMessageIdStamp('some_id')]);
        $receiver->expects($this->once())->method('find')->with('some_id')->willReturn($envelope);

        $command = new FailedMessagesRemoveCommand($globalFailureReceiverName, new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver]));
        $tester = new CommandTester($command);
        $tester->execute(['id' => ['some_id'], '--force' => true], ['capture_stderr_separately' => true]);

        $stdout = $tester->getDisplay();
        $stderr = $tester->getErrorOutput();

        $this->assertStringContainsString('Message with id some_id removed', $stdout);
        $this->assertStringNotContainsString('Message with id some_id removed', $stderr);
    }

    public function testErrorMessageGoesToStderr()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $receiver->expects($this->once())->method('find')->with('not_found')->willReturn(null);

        $command = new FailedMessagesRemoveCommand($globalFailureReceiverName, new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver]));
        $tester = new CommandTester($command);
        $tester->execute(['id' => ['not_found']], ['capture_stderr_separately' => true]);

        $stdout = $tester->getDisplay();
        $stderr = $tester->getErrorOutput();

        $this->assertStringNotContainsString('[ERROR]', $stdout);
        $this->assertStringContainsString('The message with id "not_found" was not found', $stderr);
    }

    public function testNoteMessageGoesToStderr()
    {
        $globalFailureReceiverName = 'failure_receiver';
        $receiver = $this->createMock(ListableReceiverInterface::class);

        $envelope = new Envelope(new \stdClass(), [new TransportMessageIdStamp('some_id')]);
        $receiver->expects($this->once())->method('find')->with('some_id')->willReturn($envelope);

        $command = new FailedMessagesRemoveCommand($globalFailureReceiverName, new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver]));
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);
        $tester->execute(['id' => ['some_id']], ['capture_stderr_separately' => true]);

        $stdout = $tester->getDisplay();
        $stderr = $tester->getErrorOutput();

        $this->assertStringNotContainsString('[NOTE]', $stdout);
        $this->assertStringContainsString('Message with id some_id not removed', $stderr);
    }

    public function testRemoveMessagesFilteredByFailureTime()
    {
        $envelopes = [
            $this->createFailedEnvelope(10, '2024-05-01 10:00:00'),
            $this->createFailedEnvelope(20, '2024-05-02 10:00:00'),
            $this->createFailedEnvelope(30, '2024-05-03 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [20, 30]);
        $tester->execute(['--failed-after' => '2024-05-02 00:00:00', '--force' => true]);

        $this->assertStringContainsString('Can you confirm you want to remove 2 messages?', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 30 removed.', $tester->getDisplay());
    }

    public function testRemoveMessagesFilteredByRelativeFailureTime()
    {
        $envelopes = [
            $this->createFailedEnvelope(10, '-1 year'),
            $this->createFailedEnvelope(20, '-1 minute'),
        ];

        $tester = $this->createCommandTester($envelopes, [20]);
        $tester->execute(['--failed-after' => '-1 hour', '--force' => true]);

        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testRemoveMessagesFilteredByClassAndFailureTime()
    {
        $anotherClass = new class extends \stdClass {};

        $envelopes = [
            $this->createFailedEnvelope(10, '2024-05-02 10:00:00'),
            new Envelope(new $anotherClass(), [new TransportMessageIdStamp(20), new RedeliveryStamp(0, new \DateTimeImmutable('2024-05-02 11:00:00'))]),
            $this->createFailedEnvelope(30, '2024-05-05 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [10]);
        $tester->execute(['--class-filter' => 'stdClass', '--failed-before' => '2024-05-03 00:00:00', '--force' => true]);

        $this->assertStringContainsString('Can you confirm you want to remove 1 message?', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 10 removed.', $tester->getDisplay());
    }

    public function testRemoveExcludesMessagesWithoutRedeliveryStampWhenFilteringByFailureTime()
    {
        $envelopes = [
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(10)]),
            $this->createFailedEnvelope(20, '2024-05-02 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [20]);
        $tester->execute(['--failed-after' => '2024-05-01 00:00:00', '--force' => true]);

        $this->assertStringContainsString('Can you confirm you want to remove 1 message?', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
        $this->assertStringNotContainsString('Message with id 10 removed.', $tester->getDisplay());
    }

    public function testRemoveIncludesMessagesWithoutRedeliveryStampWhenFilteringByClassOnly()
    {
        $envelopes = [
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(10)]),
            $this->createFailedEnvelope(20, '2024-05-02 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [10, 20]);
        $tester->execute(['--class-filter' => 'stdClass', '--force' => true]);

        $this->assertStringContainsString('Message with id 10 removed.', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 20 removed.', $tester->getDisplay());
    }

    public function testRemoveMessagesFilteredByFailureTimeCanBeAborted()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->willReturn([
            $this->createFailedEnvelope(10, '2024-05-02 10:00:00'),
        ]);
        $receiver->expects($this->never())->method('find');
        $receiver->expects($this->never())->method('reject');

        $command = new FailedMessagesRemoveCommand('failure_receiver', new ServiceLocator(['failure_receiver' => static fn () => $receiver]));

        $tester = new CommandTester($command);
        $tester->setInputs(['no']);
        $tester->execute(['--failed-after' => '2024-05-01 00:00:00', '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Can you confirm you want to remove 1 message?', $tester->getDisplay());
    }

    public function testRemoveWithForceDoesNotAskForEachFilteredMessage()
    {
        $envelopes = [
            $this->createFailedEnvelope(10, '2024-05-02 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [10]);
        $tester->execute(['--failed-after' => '2024-05-01 00:00:00', '--force' => true]);

        $this->assertStringNotContainsString('Do you want to permanently remove this message?', $tester->getDisplay());
        $this->assertStringContainsString('Message with id 10 removed.', $tester->getDisplay());
    }

    public function testRemoveWithIdsAndFilterThrows()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $command = new FailedMessagesRemoveCommand($globalFailureReceiverName, new ServiceLocator([$globalFailureReceiverName => fn () => $this->createStub(ListableReceiverInterface::class)]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot specify message ids when using the "--class-filter", "--failed-after" or "--failed-before" options.');
        $tester->execute(['id' => [20], '--failed-after' => '2024-05-01 00:00:00']);
    }

    public function testRemoveWithFilterMatchingNothingThrows()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->willReturn([
            $this->createFailedEnvelope(10, '2024-05-02 10:00:00'),
        ]);

        $command = new FailedMessagesRemoveCommand('failure_receiver', new ServiceLocator(['failure_receiver' => static fn () => $receiver]));
        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No failed messages were found with this filter.');
        $tester->execute(['--failed-after' => '2024-06-01 00:00:00', '--force' => true]);
    }

    public function testRemoveWithUnparsableFailureTimeThrows()
    {
        $globalFailureReceiverName = 'failure_receiver';

        $command = new FailedMessagesRemoveCommand($globalFailureReceiverName, new ServiceLocator([$globalFailureReceiverName => fn () => $this->createStub(ListableReceiverInterface::class)]));
        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The value of the "--failed-before" option is not a valid date: "yesterdayish".');
        $tester->execute(['--failed-before' => 'yesterdayish']);
    }

    private function createFailedEnvelope(int $id, string $failedAt): Envelope
    {
        return new Envelope(new \stdClass(), [
            new TransportMessageIdStamp($id),
            new RedeliveryStamp(0, new \DateTimeImmutable($failedAt)),
        ]);
    }

    /**
     * @param Envelope[] $envelopes   the messages listed by the failure transport
     * @param int[]      $expectedIds the ids expected to be removed, in order
     */
    private function createCommandTester(array $envelopes, array $expectedIds): CommandTester
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->willReturn($envelopes);
        $receiver->expects($this->exactly(\count($expectedIds)))->method('find')
            ->willReturnCallback(function (...$args) use ($envelopes, &$expectedIds) {
                $this->assertSame([array_shift($expectedIds)], $args);

                foreach ($envelopes as $envelope) {
                    if ([$envelope->last(TransportMessageIdStamp::class)->getId()] === $args) {
                        return $envelope;
                    }
                }

                return null;
            })
        ;
        $receiver->expects($this->exactly(\count($expectedIds)))->method('reject');

        $command = new FailedMessagesRemoveCommand('failure_receiver', new ServiceLocator(['failure_receiver' => static fn () => $receiver]));

        return new CommandTester($command);
    }
}
