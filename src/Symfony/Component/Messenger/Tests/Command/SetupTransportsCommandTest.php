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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Command\SetupTransportsCommand;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class SetupTransportsCommandTest extends TestCase
{
    public function testReceiverNames()
    {
        $command = new SetupTransportsCommand(new ServiceLocator([
            'amqp' => fn () => $this->createStub(SetupableTransportInterface::class),
            'other_transport' => fn () => $this->createStub(TransportInterface::class),
        ]), ['amqp', 'other_transport']);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('The "amqp" transport was set up successfully.', $display);
        $this->assertStringContainsString('The "other_transport" transport does not support setup.', $display);
    }

    public function testReceiverNameArgument()
    {
        $command = new SetupTransportsCommand(new ServiceLocator(['amqp' => fn () => $this->createStub(SetupableTransportInterface::class)]), ['amqp', 'other_transport']);
        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'amqp']);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('The "amqp" transport was set up successfully.', $display);
    }

    public function testReceiverNameArgumentNotFound()
    {
        $command = new SetupTransportsCommand(new ServiceLocator([]), ['amqp', 'other_transport']);
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "not_found" transport does not exist.');
        $tester->execute(['transport' => 'not_found']);
    }

    public function testThrowsExceptionOnTransportSetup()
    {
        // mock a setupable-transport, that throws
        $amqpTransport = $this->createMock(SetupableTransportInterface::class);
        $amqpTransport->expects($this->exactly(1))
            ->method('setup')
            ->willThrowException(new \RuntimeException('Server not found'));

        $command = new SetupTransportsCommand(new ServiceLocator(['amqp' => static fn () => $amqpTransport]), ['amqp']);
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while setting up the "amqp" transport: Server not found');
        $tester->execute(['transport' => 'amqp']);
    }

    public function testInboxesAreSetUpWithTheirTransport()
    {
        $inbox = $this->createMock(SetupableTransportInterface::class);
        $inbox->expects($this->once())->method('setup');

        $command = new SetupTransportsCommand(new ServiceLocator([
            'orders' => fn () => $this->createStub(TransportInterface::class),
            'invoices' => fn () => $this->createStub(SetupableTransportInterface::class),
        ]), ['orders', 'invoices'], new ServiceLocator(['orders' => static fn () => $inbox]));
        $tester = new CommandTester($command);
        $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('The "orders" transport does not support setup.', $display);
        $this->assertStringContainsString('The inbox of the "orders" transport was set up successfully.', $display);
        $this->assertStringContainsString('The "invoices" transport was set up successfully.', $display);
        $this->assertStringNotContainsString('The inbox of the "invoices" transport', $display);
    }

    public function testThrowsExceptionOnInboxSetup()
    {
        $inbox = $this->createMock(SetupableTransportInterface::class);
        $inbox->expects($this->once())
            ->method('setup')
            ->willThrowException(new \RuntimeException('Table is locked'));

        $command = new SetupTransportsCommand(new ServiceLocator(['orders' => fn () => $this->createStub(SetupableTransportInterface::class)]), ['orders'], new ServiceLocator(['orders' => static fn () => $inbox]));
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An error occurred while setting up the inbox of the "orders" transport: Table is locked');
        $tester->execute(['transport' => 'orders']);
    }

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $command = new SetupTransportsCommand(new Container(), ['amqp', 'other_transport']);
        $tester = new CommandCompletionTester($command);
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions()
    {
        yield 'transport' => [[''], ['amqp', 'other_transport']];
    }
}
