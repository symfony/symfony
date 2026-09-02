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

use Amp\Parallel\Worker\ContextWorkerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyReceiver;
use Symfony\Component\Messenger\Tests\Fixtures\ResettableDummyReceiver;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

class ConsumeMessagesCommandTest extends TestCase
{
    public function testConfigurationWithDefaultReceiver()
    {
        $command = new ConsumeMessagesCommand(new RoutableMessageBus(new Container()), new ServiceLocator([]), new EventDispatcher(), null, ['amqp']);
        $inputArgument = $command->getDefinition()->getArgument('receivers');
        $this->assertFalse($inputArgument->isRequired());
        $this->assertSame(['amqp'], $inputArgument->getDefault());
        $this->assertTrue($command->getDefinition()->hasOption('concurrency'));
        $this->assertFalse($command->getDefinition()->hasOption('parallel-limit'));
    }

    public function testBasicRun()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('get')->willReturn([$envelope]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $receiver);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch');

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--limit' => 1,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] Consuming messages from transport "dummy-receiver"', $tester->getDisplay());
    }

    public function testRunWithBusOption()
    {
        $envelope = new Envelope(new \stdClass());

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('get')->willReturn([$envelope]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $receiver);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch');

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--bus' => 'dummy-bus',
            '--limit' => 1,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] Consuming messages from transport "dummy-receiver"', $tester->getDisplay());
    }

    public static function provideRunWithResetServicesOption(): iterable
    {
        yield [true];
        yield [false];
    }

    #[DataProvider('provideRunWithResetServicesOption')]
    public function testRunWithResetServicesOption(bool $shouldReset)
    {
        $envelope = new Envelope(new \stdClass());

        $receiver = new ResettableDummyReceiver([
            [$envelope],
            [/* idle */],
            [$envelope, $envelope],
        ]);
        $msgCount = 3;

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $receiver);

        $bus = $this->createMock(RoutableMessageBus::class);
        $bus->expects($this->exactly($msgCount))->method('dispatch');

        $servicesResetter = new ServicesResetter(new \ArrayIterator([$receiver]), ['reset']);

        $command = new ConsumeMessagesCommand($bus, $receiverLocator, new EventDispatcher(), null, [], new ResetServicesListener($servicesResetter));

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute(array_merge([
            'receivers' => ['dummy-receiver'],
            '--sleep' => '0.001', // do not sleep too long
            '--limit' => $msgCount,
        ], $shouldReset ? [] : ['--no-reset' => null]));

        $this->assertEquals($shouldReset, $receiver->hasBeenReset(), '$receiver->reset() should have been called');
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] Consuming messages from transport "dummy-receiver"', $tester->getDisplay());
    }

    public function testRunTwiceInTheSameProcess()
    {
        $envelope = new Envelope(new \stdClass());

        $receiver = $this->createStub(ReceiverInterface::class);
        $receiver->method('get')->willReturn([$envelope]);

        $receiverLocator = $this->createStub(ContainerInterface::class);
        $receiverLocator->method('has')->willReturn(true);
        $receiverLocator->method('get')->willReturn($receiver);

        $handledMessages = 0;
        $bus = $this->createStub(RoutableMessageBus::class);
        $bus->method('dispatch')->willReturnCallback(static function (Envelope $envelope) use (&$handledMessages) {
            ++$handledMessages;

            return $envelope;
        });

        $eventDispatcher = new EventDispatcher();
        $resetServicesListener = new ResetServicesListener(new ServicesResetter(new \ArrayIterator([]), []));
        $command = new ConsumeMessagesCommand($bus, $receiverLocator, $eventDispatcher, null, [], $resetServicesListener);

        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }
        $tester = new CommandTester($application->get('messenger:consume'));

        $tester->execute(['receivers' => ['dummy-receiver'], '--limit' => 1]);

        $this->assertSame(1, $handledMessages);
        $this->assertSame([], $eventDispatcher->getListeners());

        $tester->execute(['receivers' => ['dummy-receiver'], '--limit' => 2]);

        $this->assertSame(3, $handledMessages);
    }

    #[DataProvider('getInvalidOptions')]
    public function testRunWithInvalidOption(string $option, string $value, string $expectedMessage)
    {
        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', new \stdClass());

        $command = new ConsumeMessagesCommand(new RoutableMessageBus(new Container()), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage($expectedMessage);
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            $option => $value,
        ]);
    }

    public static function getInvalidOptions()
    {
        yield 'Zero message limit' => ['--limit', '0', 'Option "limit" must be a positive integer, "0" passed.'];
        yield 'Non-numeric message limit' => ['--limit', 'whatever', 'Option "limit" must be a positive integer, "whatever" passed.'];

        yield 'Zero second time limit' => ['--time-limit', '0', 'Option "time-limit" must be a positive integer, "0" passed.'];
        yield 'Non-numeric time limit' => ['--time-limit', 'whatever', 'Option "time-limit" must be a positive integer, "whatever" passed.'];
        yield 'Negative reset interval' => ['--no-reset', '-1', 'Option "no-reset" must be a positive integer, "-1" passed.'];
        yield 'Zero concurrency' => ['--concurrency', '0', 'Option "concurrency" must be a positive integer, "0" passed.'];
        yield 'Negative concurrency' => ['--concurrency', '-1', 'Option "concurrency" must be a positive integer, "-1" passed.'];
        yield 'Non-numeric concurrency' => ['--concurrency', 'abc', 'Option "concurrency" must be a positive integer, "abc" passed.'];
        yield 'Zero fetch size' => ['--fetch-size', '0', 'Option "fetch-size" must be a positive integer, "0" passed.'];
        yield 'Negative fetch size' => ['--fetch-size', '-1', 'Option "fetch-size" must be a positive integer, "-1" passed.'];
        yield 'Non-numeric fetch size' => ['--fetch-size', 'abc', 'Option "fetch-size" must be a positive integer, "abc" passed.'];
    }

    public function testParallelExecutionRequiresConfiguredConsoleEntryPoint()
    {
        if (!class_exists(ContextWorkerFactory::class)) {
            $this->markTestSkipped(ContextWorkerFactory::class.' is not available.');
        }

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $this->createStub(ReceiverInterface::class));

        $command = new ConsumeMessagesCommand(new RoutableMessageBus(new Container()), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parallel message execution requires the console entry point to be configured.');
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--concurrency' => 2,
        ]);
    }

    public function testRunWithFetchSizeOption()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = new DummyReceiver([[$envelope]]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $receiver);

        $busLocator = new Container();
        $busLocator->set('dummy-bus', new MessageBus());

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--fetch-size' => '8',
            '--limit' => 1,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame([8], $receiver->getFetchSizes());
    }

    public function testRunWithoutReceiverInNonInteractiveMode()
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) {
            $event->getWorker()->stop();
        });

        $command = new ConsumeMessagesCommand(new RoutableMessageBus(new Container()), new ServiceLocator([]), $eventDispatcher, null, ['dummy-receiver', 'another-receiver']);

        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }
        $tester = new CommandTester($application->get('messenger:consume'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please pass at least one receiver.');

        $tester->execute([], ['interactive' => false]);
    }

    public function testRunWithTimeLimit()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = $this->createStub(ReceiverInterface::class);
        $receiver->method('get')->willReturn([$envelope]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $receiver);

        $busLocator = new Container();
        $busLocator->set('dummy-bus', new MessageBus());

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--time-limit' => 1,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Consuming messages from transport "dummy-receiver"', $tester->getDisplay());
    }

    public function testRunWithMemoryLimit()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = $this->createStub(ReceiverInterface::class);
        $receiver->method('get')->willReturn([$envelope]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $receiver);

        $bus = $this->createStub(MessageBusInterface::class);

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $logger = new class implements LoggerInterface {
            use LoggerTrait;

            public array $logs = [];

            public function log(...$args): void
            {
                $this->logs[] = $args;
            }
        };
        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher(), $logger);

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--memory-limit' => '1.5M',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Consuming messages from transport "dummy-receiver"', $tester->getDisplay());
        $this->assertStringContainsString('The worker will automatically exit once it has exceeded 1.5M of memory', $tester->getDisplay());

        $this->assertSame(1572864, $logger->logs[1][2]['limit']);
    }

    public function testRunWithAllOption()
    {
        $envelope1 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);
        $envelope2 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver1 = $this->createStub(ReceiverInterface::class);
        $receiver1->method('get')->willReturn([$envelope1]);
        $receiver2 = $this->createStub(ReceiverInterface::class);
        $receiver2->method('get')->willReturn([$envelope2]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver1', $receiver1);
        $receiverLocator->set('dummy-receiver2', $receiver2);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))->method('dispatch');

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $command = new ConsumeMessagesCommand(
            new RoutableMessageBus($busLocator),
            $receiverLocator, new EventDispatcher(),
            receiverNames: ['dummy-receiver1', 'dummy-receiver2']
        );

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            '--all' => true,
            '--limit' => 2,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] Consuming messages from transports "dummy-receiver1, dummy-receiver2"', $tester->getDisplay());
    }

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $command = new ConsumeMessagesCommand(new RoutableMessageBus(new Container()), new Container(), new EventDispatcher(), null, ['async', 'async_high', 'failed'], null, ['messenger.bus.default']);
        $tester = new CommandCompletionTester($command);
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions()
    {
        yield 'receiver' => [[''], ['async', 'async_high', 'failed']];
        yield 'receiver (value)' => [['async'], ['async', 'async_high', 'failed']];
        yield 'receiver (no repeat)' => [['async', ''], ['async_high', 'failed']];
        yield 'option --bus' => [['--bus', ''], ['messenger.bus.default']];
    }

    public function testSuccessMessageGoesToStdout()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('get')->willReturn([$envelope]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $receiver);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch');

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--limit' => 1,
        ], ['capture_stderr_separately' => true]);

        $stdout = $tester->getDisplay();
        $stderr = $tester->getErrorOutput();

        $this->assertStringContainsString('Consuming messages from transport', $stdout);
        $this->assertStringNotContainsString('Consuming messages from transport', $stderr);
    }

    public function testCommentsGoToStderr()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('get')->willReturn([$envelope]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', $receiver);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch');

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--limit' => 1,
        ], ['capture_stderr_separately' => true]);

        $stdout = $tester->getDisplay();
        $stderr = $tester->getErrorOutput();

        $this->assertStringNotContainsString('Quit the worker with CONTROL-C', $stdout);
        $this->assertStringContainsString('Quit the worker with CONTROL-C', $stderr);
    }

    public function testRunWithExcludeReceiversOption()
    {
        $envelope1 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);
        $envelope2 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);
        $envelope3 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver1 = $this->createStub(ReceiverInterface::class);
        $receiver1->method('get')->willReturn([$envelope1]);
        $receiver2 = $this->createStub(ReceiverInterface::class);
        $receiver2->method('get')->willReturn([$envelope2]);
        $receiver3 = $this->createStub(ReceiverInterface::class);
        $receiver3->method('get')->willReturn([$envelope3]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver1', $receiver1);
        $receiverLocator->set('dummy-receiver2', $receiver2);
        $receiverLocator->set('dummy-receiver3', $receiver3);

        $bus = $this->createMock(MessageBusInterface::class);
        // Only 2 messages should be dispatched (receiver1 and receiver3, receiver2 is excluded)
        $bus->expects($this->exactly(2))->method('dispatch');

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $command = new ConsumeMessagesCommand(
            new RoutableMessageBus($busLocator),
            $receiverLocator, new EventDispatcher(),
            receiverNames: ['dummy-receiver1', 'dummy-receiver2', 'dummy-receiver3']
        );

        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            '--all' => true,
            '--exclude-receivers' => ['dummy-receiver2'],
            '--limit' => 2,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] Consuming messages from transports "dummy-receiver1, dummy-receiver3"', $tester->getDisplay());
    }

    public function testRunWithExcludeReceiversMultipleQueues()
    {
        $envelope1 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);
        $envelope2 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);
        $envelope3 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);
        $envelope4 = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver1 = $this->createStub(ReceiverInterface::class);
        $receiver1->method('get')->willReturn([$envelope1]);
        $receiver2 = $this->createStub(ReceiverInterface::class);
        $receiver2->method('get')->willReturn([$envelope2]);
        $receiver3 = $this->createStub(ReceiverInterface::class);
        $receiver3->method('get')->willReturn([$envelope3]);
        $receiver4 = $this->createStub(ReceiverInterface::class);
        $receiver4->method('get')->willReturn([$envelope4]);

        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver1', $receiver1);
        $receiverLocator->set('dummy-receiver2', $receiver2);
        $receiverLocator->set('dummy-receiver3', $receiver3);
        $receiverLocator->set('dummy-receiver4', $receiver4);

        $bus = $this->createMock(MessageBusInterface::class);
        // Only 2 messages should be dispatched (receiver1 and receiver4, receiver2 and receiver3 are excluded)
        $bus->expects($this->exactly(2))->method('dispatch');

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $command = new ConsumeMessagesCommand(
            new RoutableMessageBus($busLocator),
            $receiverLocator, new EventDispatcher(),
            receiverNames: ['dummy-receiver1', 'dummy-receiver2', 'dummy-receiver3', 'dummy-receiver4']
        );

        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            '--all' => true,
            '--exclude-receivers' => ['dummy-receiver2', 'dummy-receiver3'],
            '--limit' => 2,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] Consuming messages from transports "dummy-receiver1, dummy-receiver4"', $tester->getDisplay());
    }

    public function testExcludeReceiverssWithoutAllOptionThrowsException()
    {
        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver', new \stdClass());

        $command = new ConsumeMessagesCommand(new RoutableMessageBus(new Container()), $receiverLocator, new EventDispatcher());

        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }
        $tester = new CommandTester($application->get('messenger:consume'));

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The "--exclude-receivers" option can only be used with the "--all" option.');
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--exclude-receivers' => ['dummy-receiver'],
        ]);
    }

    public function testExcludeReceiversWithAllQueuesExcludedThrowsException()
    {
        $receiverLocator = new Container();
        $receiverLocator->set('dummy-receiver1', new \stdClass());
        $receiverLocator->set('dummy-receiver2', new \stdClass());

        $command = new ConsumeMessagesCommand(
            new RoutableMessageBus(new Container()),
            $receiverLocator,
            new EventDispatcher(),
            receiverNames: ['dummy-receiver1', 'dummy-receiver2']
        );

        $application = new Application();
        if (method_exists($application, 'addCommand')) {
            $application->addCommand($command);
        } else {
            $application->add($command);
        }
        $tester = new CommandTester($application->get('messenger:consume'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All transports/receivers have been excluded, please specify at least one to consume from.');
        $tester->execute([
            '--all' => true,
            '--exclude-receivers' => ['dummy-receiver1', 'dummy-receiver2'],
        ]);
    }

    #[DataProvider('provideReceiversForCaseInsensitiveMatching')]
    public function testCaseInsensitiveReceiverMatching(array $receiverNames, array $receivers, array $expectedReceivers)
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = $this->createStub(ReceiverInterface::class);
        $receiver->method('get')->willReturn([$envelope]);

        $receiverLocator = new Container();
        foreach ($receiverNames as $receiverName) {
            $receiverLocator->set($receiverName, $receiver);
        }

        $bus = $this->createStub(MessageBusInterface::class);

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher(), receiverNames: $receiverNames);

        $application = new Application();
        $application->addCommand($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => $receivers,
            '--limit' => 1,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString(
            \sprintf(
                '[OK] Consuming messages from %s "%s"',
                1 === \count($expectedReceivers) ? 'transport' : 'transports',
                implode(', ', $expectedReceivers)
            ),
            $tester->getDisplay()
        );
    }

    public static function provideReceiversForCaseInsensitiveMatching(): \Traversable
    {
        yield [['one', 'one_second', 'second', 'pone'], ['one.*'], ['one', 'one_second']];
        yield [['one', 'one_second', 'second'], ['one'], ['one']];
        yield [['one', 'one_second', 'second', 'SECOND'], ['(?i)second'], ['second', 'SECOND']];
        yield [['one', 'one_second', 'second', 'SECOND', 'ssecond'], ['one', 'one.*', 'second.*'], ['one', 'one_second', 'second']];
        yield [['pone'], ['.*one'], ['pone']];
    }

    #[RequiresPhpExtension('pcntl')]
    public function testHandleSignalWithoutRunningWorker()
    {
        $command = new ConsumeMessagesCommand(new RoutableMessageBus(new Container()), new Container(), new EventDispatcher());

        $this->assertFalse($command->handleSignal(\SIGTERM));
    }

    #[RequiresPhpExtension('pcntl')]
    public function testFirstSignalStopsTheWorkerGracefully()
    {
        $exitCodes = [];
        $tester = $this->createSignalableCommandTester(static function (ConsumeMessagesCommand $command) use (&$exitCodes) {
            $exitCodes[] = $command->handleSignal(\SIGTERM);
        });

        $tester->execute(['receivers' => ['dummy-receiver']]);

        $this->assertSame([false], $exitCodes);
        $tester->assertCommandIsSuccessful();
    }

    #[RequiresPhpExtension('pcntl')]
    public function testSecondSignalForcesTheWorkerToQuit()
    {
        $exitCodes = [];
        $tester = $this->createSignalableCommandTester(static function (ConsumeMessagesCommand $command) use (&$exitCodes) {
            $exitCodes[] = $command->handleSignal(\SIGTERM);
            $exitCodes[] = $command->handleSignal(\SIGINT);
            $exitCodes[] = $command->handleSignal(\SIGINT, 3);
            $exitCodes[] = $command->handleSignal(\SIGINT, false);
        });

        $tester->execute(['receivers' => ['dummy-receiver']]);

        $this->assertSame([false, 128 + \SIGINT, 3, false], $exitCodes);
    }

    #[RequiresPhpExtension('pcntl')]
    public function testOnlySigintReceivedWhileStoppingForcesTheWorkerToQuit()
    {
        $exitCodes = [];
        $tester = $this->createSignalableCommandTester(static function (ConsumeMessagesCommand $command) use (&$exitCodes) {
            $exitCodes[] = $command->handleSignal(\SIGALRM);
            $exitCodes[] = $command->handleSignal(\SIGINT);
            $exitCodes[] = $command->handleSignal(\SIGTERM);
            $exitCodes[] = $command->handleSignal(\SIGQUIT);
            $exitCodes[] = $command->handleSignal(\SIGALRM);
            $exitCodes[] = $command->handleSignal(\SIGINT);
        });

        $tester->execute(['receivers' => ['dummy-receiver']]);

        $this->assertSame([false, false, false, false, false, 128 + \SIGINT], $exitCodes);
    }

    #[RequiresPhpExtension('pcntl')]
    public function testSignalStaysGracefulWhenTheWorkerWasStoppedWithoutASignal()
    {
        $exitCodes = [];
        $tester = $this->createSignalableCommandTester(static function (ConsumeMessagesCommand $command, Worker $worker) use (&$exitCodes) {
            $worker->stop();
            $exitCodes[] = $command->handleSignal(\SIGINT);
        });

        $tester->execute(['receivers' => ['dummy-receiver']]);

        $this->assertSame([false], $exitCodes);
        $tester->assertCommandIsSuccessful();
    }

    private function createSignalableCommandTester(callable $onMessage): CommandTester
    {
        $command = null;
        $receiver = new DummyReceiver([[new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')])]]);

        $worker = null;

        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (Envelope $envelope) use (&$command, &$worker, $onMessage) {
            $onMessage($command, $worker);

            return $envelope;
        });

        $busLocator = new Container();
        $busLocator->set('dummy-bus', $bus);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(WorkerStartedEvent::class, static function (WorkerStartedEvent $event) use (&$worker) {
            $worker = $event->getWorker();
        });

        $command = new ConsumeMessagesCommand(
            new RoutableMessageBus($busLocator),
            new ServiceLocator(['dummy-receiver' => static fn () => $receiver]),
            $eventDispatcher
        );

        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->get('messenger:consume'));
    }
}
