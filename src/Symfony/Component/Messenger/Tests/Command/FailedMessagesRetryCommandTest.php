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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

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

        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            new ServiceLocator([$failureTransportName => static fn () => $receiver]),
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
        $receiver = $this->createStub(ListableReceiverInterface::class);
        $receiver->method('all')->willReturn([]);

        $dispatcher = new EventDispatcher();

        $failureTransportName = 'failure_receiver';

        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            new ServiceLocator([
                $failureTransportName => static fn () => $receiver,
                'failure_receiver_2' => static fn () => $receiver,
                'failure_receiver_3' => static fn () => $receiver,
            ]),
            new MessageBus(),
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

        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            new ServiceLocator([$failureTransportName => static fn () => $receiver]),
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

        $receiver = $this->createStub(ListableReceiverInterface::class);

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            new ServiceLocator([
                'global_receiver' => static fn () => $receiver,
                $globalFailureReceiverName => static fn () => $receiver,
            ]),
            new MessageBus(),
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

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$globalFailureReceiverName => static fn () => $receiver]),
            new MessageBus(),
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

        $command = new FailedMessagesRetryCommand(
            $globalFailureReceiverName,
            new ServiceLocator([$anotherFailureReceiverName => static fn () => $receiver]),
            new MessageBus(),
            new EventDispatcher()
        );
        $tester = new CommandCompletionTester($command);

        $suggestions = $tester->complete(['--transport', $anotherFailureReceiverName, ' ']);

        $this->assertSame(['2ab50dfa1fbf', '78c2da843723'], $suggestions);
    }

    public function testSuccessMessageGoesToStdout()
    {
        $envelope = new Envelope(new \stdClass(), [new TransportMessageIdStamp('some_id')]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with('some_id')->willReturn($envelope);

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            new MessageBus(),
            new EventDispatcher()
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['retry']);
        $tester->execute(['id' => ['some_id']], ['capture_stderr_separately' => true]);

        $stdout = $tester->getDisplay();
        $stderr = $tester->getErrorOutput();

        $this->assertStringContainsString('All done!', $stdout);
        $this->assertStringNotContainsString('All done!', $stderr);
    }

    public function testCommentsGoToStderr()
    {
        $envelope = new Envelope(new \stdClass(), [new TransportMessageIdStamp('some_id')]);
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with('some_id')->willReturn($envelope);

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            new MessageBus(),
            new EventDispatcher()
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['retry']);
        $tester->execute(['id' => ['some_id']], ['capture_stderr_separately' => true]);

        $stdout = $tester->getDisplay();
        $stderr = $tester->getErrorOutput();

        $this->assertStringContainsString('Quit this command with CONTROL-C', $stderr);
        $this->assertStringNotContainsString('Quit this command with CONTROL-C', $stdout);
    }

    public function testPendingMessageCountGoesToStdout()
    {
        $receiver = new class implements ListableReceiverInterface, MessageCountAwareInterface {
            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }

            public function find(mixed $id): ?Envelope
            {
                return null;
            }

            public function all(?int $limit = null): iterable
            {
                return [];
            }

            public function getMessageCount(): int
            {
                return 5;
            }
        };

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            new MessageBus(),
            new EventDispatcher()
        );

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true], ['capture_stderr_separately' => true]);

        $stdout = $tester->getDisplay();
        $stderr = $tester->getErrorOutput();

        $this->assertStringContainsString('There are', $stdout);
        $this->assertStringContainsString('5', $stdout);
        $this->assertStringContainsString('messages pending', $stdout);
        $this->assertStringNotContainsString('messages pending', $stderr);
    }

    public function testSkipRunWithServiceLocator()
    {
        $failureTransportName = 'failure_receiver';
        $originalTransportName = 'original_receiver';

        $receiver = $this->createMock(ListableReceiverInterface::class);

        $dispatcher = new EventDispatcher();

        $receiver->expects($this->once())->method('find')
            ->willReturn(Envelope::wrap(new \stdClass(), [
                new SentToFailureTransportStamp($originalTransportName),
            ]));

        $receiver->expects($this->never())->method('ack');
        $receiver->expects($this->once())->method('reject');

        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            new ServiceLocator([$failureTransportName => static fn () => $receiver]),
            new MessageBus(),
            $dispatcher
        );

        $tester = new CommandTester($command);
        $tester->setInputs(['skip']);

        $tester->execute(['id' => ['10']]);
        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testRedispatchRunWithServiceLocator()
    {
        $message = new \stdClass();
        $redeliveryStamp = new RedeliveryStamp(2);
        $envelope = new Envelope($message, [
            new ReceivedStamp('async'),
            new ConsumedByWorkerStamp(),
            new SentToFailureTransportStamp('async'),
            new TransportMessageIdStamp('failed-id'),
            $redeliveryStamp,
        ]);

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with('failed-id')->willReturn($envelope);
        $receiver->expects($this->once())->method('ack')->with($envelope);
        $receiver->expects($this->never())->method('reject');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')
            ->with($this->callback(function (Envelope $dispatchedEnvelope) use ($message, $redeliveryStamp) {
                $this->assertSame($message, $dispatchedEnvelope->getMessage());
                $this->assertNull($dispatchedEnvelope->last(ReceivedStamp::class));
                $this->assertNull($dispatchedEnvelope->last(ConsumedByWorkerStamp::class));
                $this->assertNull($dispatchedEnvelope->last(SentToFailureTransportStamp::class));
                $this->assertNull($dispatchedEnvelope->last(TransportMessageIdStamp::class));
                $this->assertSame($redeliveryStamp, $dispatchedEnvelope->last(RedeliveryStamp::class));

                return true;
            }))
            ->willReturnCallback(static fn (Envelope $dispatchedEnvelope) => $dispatchedEnvelope);

        $failureTransportName = 'failure_receiver';
        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            new ServiceLocator([$failureTransportName => static fn () => $receiver]),
            $bus,
            new EventDispatcher(),
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => ['failed-id'], '--force' => true, '--redispatch' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testRedispatchKeepsTheMessageWhenTheBusThrows()
    {
        $envelope = new Envelope(new \stdClass(), [
            new SentToFailureTransportStamp('async'),
            new TransportMessageIdStamp('failed-id'),
        ]);

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->exactly(2))->method('find')->willReturn($envelope);
        $receiver->expects($this->never())->method('ack');
        $receiver->expects($this->never())->method('reject');

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('Transport is down'));

        $failureTransportName = 'failure_receiver';
        $command = new FailedMessagesRetryCommand(
            $failureTransportName,
            new ServiceLocator([$failureTransportName => static fn () => $receiver]),
            $bus,
            new EventDispatcher(),
        );

        $tester = new CommandTester($command);
        $tester->execute(['id' => ['failed-id', 'other-id'], '--force' => true, '--redispatch' => true]);

        // every id is still attempted, and the run does not report success
        $this->assertStringContainsString('Transport is down', $tester->getDisplay());
        $this->assertStringNotContainsString('[OK] All done!', $tester->getDisplay());
    }

    public function testRetryMessagesFilteredByClass()
    {
        $anotherClass = new class extends \stdClass {};

        $envelopes = [
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(10)]),
            new Envelope(new $anotherClass(), [new TransportMessageIdStamp(20)]),
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(30)]),
        ];

        $tester = $this->createCommandTester($envelopes, [10, 30]);
        $tester->execute(['--class-filter' => 'stdClass', '--force' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testRetryMessagesFilteredByFailureTime()
    {
        $envelopes = [
            $this->createFailedEnvelope(10, '2024-05-01 10:00:00'),
            $this->createFailedEnvelope(20, '2024-05-02 10:00:00'),
            $this->createFailedEnvelope(30, '2024-05-03 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [20]);
        $tester->execute(['--failed-after' => '2024-05-02 00:00:00', '--failed-before' => '2024-05-02 23:59:59', '--force' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testRetryMessagesFilteredByRelativeFailureTime()
    {
        $envelopes = [
            $this->createFailedEnvelope(10, '-1 year'),
            $this->createFailedEnvelope(20, '-1 minute'),
        ];

        $tester = $this->createCommandTester($envelopes, [20]);
        $tester->execute(['--failed-after' => '-1 hour', '--force' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testRetryMessagesFilteredByClassAndFailureTime()
    {
        $anotherClass = new class extends \stdClass {};

        $envelopes = [
            $this->createFailedEnvelope(10, '2024-05-02 10:00:00'),
            new Envelope(new $anotherClass(), [new TransportMessageIdStamp(20), new RedeliveryStamp(0, new \DateTimeImmutable('2024-05-02 11:00:00'))]),
            $this->createFailedEnvelope(30, '2024-05-05 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [10]);
        $tester->execute(['--class-filter' => 'stdClass', '--failed-before' => '2024-05-03 00:00:00', '--force' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testRetryExcludesMessagesWithoutRedeliveryStampWhenFilteringByFailureTime()
    {
        $envelopes = [
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(10)]),
            $this->createFailedEnvelope(20, '2024-05-02 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [20]);
        $tester->execute(['--failed-after' => '2024-05-01 00:00:00', '--force' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testRetryIncludesMessagesWithoutRedeliveryStampWhenFilteringByClassOnly()
    {
        $envelopes = [
            new Envelope(new \stdClass(), [new TransportMessageIdStamp(10)]),
            $this->createFailedEnvelope(20, '2024-05-02 10:00:00'),
        ];

        $tester = $this->createCommandTester($envelopes, [10, 20]);
        $tester->execute(['--class-filter' => 'stdClass', '--force' => true]);

        $this->assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testRetryWithIdsAndFilterThrows()
    {
        $receiver = $this->createStub(ListableReceiverInterface::class);

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            new MessageBus(),
            new EventDispatcher()
        );

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot specify message ids when using the "--class-filter", "--failed-after" or "--failed-before" options.');
        $tester->execute(['id' => [10], '--class-filter' => 'stdClass']);
    }

    public function testRetryWithFilterMatchingNothingThrows()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->willReturn([
            $this->createFailedEnvelope(10, '2024-05-02 10:00:00'),
        ]);

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            new MessageBus(),
            new EventDispatcher()
        );

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No failed messages were found with this filter.');
        $tester->execute(['--failed-after' => '2024-06-01 00:00:00', '--force' => true]);
    }

    public function testRetryWithFilterOnNonListableReceiverThrows()
    {
        $receiver = $this->createStub(ReceiverInterface::class);

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            new MessageBus(),
            new EventDispatcher()
        );

        $tester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "failure_receiver" receiver does not support filtering messages.');
        $tester->execute(['--class-filter' => 'stdClass', '--force' => true]);
    }

    public function testRetryWithUnparsableFailureTimeThrows()
    {
        $receiver = $this->createStub(ListableReceiverInterface::class);

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            new MessageBus(),
            new EventDispatcher()
        );

        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The value of the "--failed-after" option is not a valid date: "not a date".');
        $tester->execute(['--failed-after' => 'not a date']);
    }

    #[RequiresPhpExtension('pcntl')]
    public function testHandleSignalWithoutRunningWorker()
    {
        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator([]),
            $this->createStub(MessageBusInterface::class),
            new EventDispatcher(),
        );

        $this->assertFalse($command->handleSignal(\SIGTERM));
    }

    #[RequiresPhpExtension('pcntl')]
    public function testFirstSignalStopsRetryingTheRemainingMessages()
    {
        $exitCodes = [];
        $tester = $this->createSignalableCommandTester(static function (FailedMessagesRetryCommand $command) use (&$exitCodes) {
            $exitCodes[] = $command->handleSignal(\SIGTERM);
        });

        $tester->execute(['id' => ['failed-id', 'other-id'], '--force' => true]);

        $this->assertSame([false], $exitCodes);
        $this->assertStringNotContainsString('[OK] All done!', $tester->getDisplay());
    }

    #[RequiresPhpExtension('pcntl')]
    public function testSecondSignalForcesTheQuit()
    {
        $exitCodes = [];
        $tester = $this->createSignalableCommandTester(static function (FailedMessagesRetryCommand $command) use (&$exitCodes) {
            $exitCodes[] = $command->handleSignal(\SIGTERM);
            $exitCodes[] = $command->handleSignal(\SIGINT);
            $exitCodes[] = $command->handleSignal(\SIGINT, 3);
            $exitCodes[] = $command->handleSignal(\SIGINT, false);
        });

        $tester->execute(['id' => ['failed-id'], '--force' => true]);

        $this->assertSame([false, 128 + \SIGINT, 3, false], $exitCodes);
    }

    #[RequiresPhpExtension('pcntl')]
    public function testOnlySigintReceivedWhileStoppingForcesTheQuit()
    {
        $exitCodes = [];
        $tester = $this->createSignalableCommandTester(static function (FailedMessagesRetryCommand $command) use (&$exitCodes) {
            $exitCodes[] = $command->handleSignal(\SIGALRM);
            $exitCodes[] = $command->handleSignal(\SIGINT);
            $exitCodes[] = $command->handleSignal(\SIGTERM);
            $exitCodes[] = $command->handleSignal(\SIGQUIT);
            $exitCodes[] = $command->handleSignal(\SIGALRM);
            $exitCodes[] = $command->handleSignal(\SIGINT);
        });

        $tester->execute(['id' => ['failed-id'], '--force' => true]);

        $this->assertSame([false, false, false, false, false, 128 + \SIGINT], $exitCodes);
    }

    #[RequiresPhpExtension('pcntl')]
    public function testSignalStaysGracefulWhenTheWorkerWasStoppedWithoutASignal()
    {
        $exitCodes = [];
        $tester = $this->createSignalableCommandTester(static function (FailedMessagesRetryCommand $command, Worker $worker) use (&$exitCodes) {
            $worker->stop();
            $exitCodes[] = $command->handleSignal(\SIGINT);
        });

        $tester->execute(['id' => ['failed-id'], '--force' => true]);

        $this->assertSame([false], $exitCodes);
        $this->assertStringNotContainsString('[OK] All done!', $tester->getDisplay());
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
     * @param int[]      $expectedIds the ids expected to be retried, in order
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
        $receiver->expects($this->exactly(\count($expectedIds)))->method('ack');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(\count($expectedIds)))->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            $bus,
            new EventDispatcher()
        );

        return new CommandTester($command);
    }

    private function createSignalableCommandTester(callable $onMessage): CommandTester
    {
        $command = null;

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->willReturn(new Envelope(new \stdClass(), [
            new SentToFailureTransportStamp('async'),
            new TransportMessageIdStamp('failed-id'),
        ]));

        $worker = null;

        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (Envelope $envelope) use (&$command, &$worker, $onMessage) {
            $onMessage($command, $worker);

            return $envelope;
        });

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WorkerStartedEvent::class, static function (WorkerStartedEvent $event) use (&$worker) {
            $worker = $event->getWorker();
        });

        $command = new FailedMessagesRetryCommand(
            'failure_receiver',
            new ServiceLocator(['failure_receiver' => static fn () => $receiver]),
            $bus,
            $eventDispatcher,
        );

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->get('messenger:failed:retry'));
    }
}
