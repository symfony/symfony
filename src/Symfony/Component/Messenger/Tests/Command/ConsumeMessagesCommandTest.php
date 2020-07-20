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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
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

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Consuming messages from transports "dummy-receiver"', $tester->getDisplay());
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

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('[OK] Consuming messages from transports "dummy-receiver"', $tester->getDisplay());
    }
}
