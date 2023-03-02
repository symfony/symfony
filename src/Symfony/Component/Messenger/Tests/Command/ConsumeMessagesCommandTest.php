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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Tests\ResettableDummyReceiver;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class ConsumeMessagesCommandTest extends TestCase
{
    public function testConfigurationWithDefaultReceiver()
    {
        $command = new ConsumeMessagesCommand($this->createMock(RoutableMessageBus::class), $this->createMock(ServiceLocator::class), $this->createMock(EventDispatcherInterface::class), null, ['amqp']);
        $inputArgument = $command->getDefinition()->getArgument('receivers');
        $this->assertFalse($inputArgument->isRequired());
        $this->assertSame(['amqp'], $inputArgument->getDefault());
    }

    public function testBasicRun()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->expects($this->once())->method('get')->willReturn([$envelope]);

        $receiverLocator = $this->createMock(ContainerInterface::class);
        $receiverLocator->expects($this->once())->method('has')->with('dummy-receiver')->willReturn(true);
        $receiverLocator->expects($this->once())->method('get')->with('dummy-receiver')->willReturn($receiver);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch');

        $busLocator = $this->createMock(ContainerInterface::class);
        $busLocator->expects($this->once())->method('has')->with('dummy-bus')->willReturn(true);
        $busLocator->expects($this->once())->method('get')->with('dummy-bus')->willReturn($bus);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->add($command);
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

        $receiverLocator = $this->createMock(ContainerInterface::class);
        $receiverLocator->expects($this->once())->method('has')->with('dummy-receiver')->willReturn(true);
        $receiverLocator->expects($this->once())->method('get')->with('dummy-receiver')->willReturn($receiver);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch');

        $busLocator = $this->createMock(ContainerInterface::class);
        $busLocator->expects($this->once())->method('has')->with('dummy-bus')->willReturn(true);
        $busLocator->expects($this->once())->method('get')->with('dummy-bus')->willReturn($bus);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->add($command);
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

    /**
     * @dataProvider provideRunWithResetServicesOption
     */
    public function testRunWithResetServicesOption(bool $shouldReset)
    {
        $envelope = new Envelope(new \stdClass());

        $receiver = new ResettableDummyReceiver([
            [$envelope],
            [/* idle */],
            [$envelope, $envelope],
        ]);
        $msgCount = 3;

        $receiverLocator = $this->createMock(ContainerInterface::class);
        $receiverLocator->expects($this->once())->method('has')->with('dummy-receiver')->willReturn(true);
        $receiverLocator->expects($this->once())->method('get')->with('dummy-receiver')->willReturn($receiver);

        $bus = $this->createMock(RoutableMessageBus::class);
        $bus->expects($this->exactly($msgCount))->method('dispatch');

        $servicesResetter = new ServicesResetter(new \ArrayIterator([$receiver]), ['reset']);

        $command = new ConsumeMessagesCommand($bus, $receiverLocator, new EventDispatcher(), null, [], new ResetServicesListener($servicesResetter));

        $application = new Application();
        $application->add($command);
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

    /**
     * @dataProvider getInvalidOptions
     */
    public function testRunWithInvalidOption(string $option, string $value, string $expectedMessage)
    {
        $receiverLocator = $this->createMock(ContainerInterface::class);
        $receiverLocator->expects($this->once())->method('has')->with('dummy-receiver')->willReturn(true);

        $busLocator = $this->createMock(ContainerInterface::class);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->add($command);
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
    }

    public function testRunWithTimeLimit()
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('dummy-bus')]);

        $receiver = $this->createMock(ReceiverInterface::class);
        $receiver->method('get')->willReturn([$envelope]);

        $receiverLocator = $this->createMock(ContainerInterface::class);
        $receiverLocator->method('has')->with('dummy-receiver')->willReturn(true);
        $receiverLocator->method('get')->with('dummy-receiver')->willReturn($receiver);

        $bus = $this->createMock(MessageBusInterface::class);

        $busLocator = $this->createMock(ContainerInterface::class);
        $busLocator->method('has')->with('dummy-bus')->willReturn(true);
        $busLocator->method('get')->with('dummy-bus')->willReturn($bus);

        $command = new ConsumeMessagesCommand(new RoutableMessageBus($busLocator), $receiverLocator, new EventDispatcher());

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('messenger:consume'));
        $tester->execute([
            'receivers' => ['dummy-receiver'],
            '--time-limit' => 1,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Consuming messages from transport "dummy-receiver"', $tester->getDisplay());
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $bus = $this->createMock(RoutableMessageBus::class);
        $receiverLocator = $this->createMock(ContainerInterface::class);
        $command = new ConsumeMessagesCommand($bus, $receiverLocator, new EventDispatcher(), null, ['async', 'async_high', 'failed'], null, ['messenger.bus.default']);
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
}
