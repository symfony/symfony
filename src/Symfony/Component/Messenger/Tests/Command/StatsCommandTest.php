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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Command\StatsCommand;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Kévin Thérage <therage.kevin@gmail.com>
 */
class StatsCommandTest extends TestCase
{
    private StatsCommand $command;

    protected function setUp(): void
    {
        $messageCountableTransport = $this->createMock(MessageCountAwareInterface::class);
        $messageCountableTransport->method('getMessageCount')->willReturn(6);

        $simpleTransport = $this->createMock(TransportInterface::class);

        // mock a service locator
        /** @var MockObject&ServiceLocator $serviceLocator */
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->method('get')
            ->willReturnCallback(function (string $transportName) use ($messageCountableTransport, $simpleTransport) {
                if (\in_array($transportName, ['message_countable', 'another_message_countable'], true)) {
                    return $messageCountableTransport;
                }

                return $simpleTransport;
            });
        $serviceLocator
            ->method('has')
            ->willReturnCallback(fn (string $transportName) => \in_array($transportName, ['message_countable', 'simple', 'another_message_countable'], true))
        ;

        $this->command = new StatsCommand($serviceLocator, [
            'message_countable',
            'simple',
            'another_message_countable',
            'unexisting',
        ]);
    }

    public function testWithoutArgument()
    {
        $tester = new CommandTester($this->command);
        $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('[WARNING] The "unexisting" transport does not exist.', $display);
        $this->assertStringContainsString('message_countable           6', $display);
        $this->assertStringContainsString('another_message_countable   6', $display);
        $this->assertStringContainsString('! [NOTE] Unable to get message count for the following transports: "simple".', $display);
    }

    public function testWithOneExistingMessageCountableTransport()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['transport_names' => ['message_countable']]);
        $display = $tester->getDisplay();

        $this->assertStringNotContainsString('[WARNING] The "unexisting" transport does not exist.', $display);
        $this->assertStringContainsString('message_countable   6', $display);
        $this->assertStringNotContainsString('another_message_countable', $display);
        $this->assertStringNotContainsString(' ! [NOTE] Unable to get message count for the following transports: "simple".', $display);
    }

    public function testWithMultipleExistingMessageCountableTransport()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['transport_names' => ['message_countable', 'another_message_countable']]);
        $display = $tester->getDisplay();

        $this->assertStringNotContainsString('[WARNING] The "unexisting" transport does not exist.', $display);
        $this->assertStringContainsString('message_countable           6', $display);
        $this->assertStringContainsString('another_message_countable   6', $display);
        $this->assertStringNotContainsString('! [NOTE] Unable to get message count for the following transports: "simple".', $display);
    }

    public function testWithNotMessageCountableTransport()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['transport_names' => ['simple']]);
        $display = $tester->getDisplay();

        $this->assertStringNotContainsString('[WARNING] The "unexisting" transport does not exist.', $display);
        $this->assertStringNotContainsString('message_countable', $display);
        $this->assertStringNotContainsString('another_message_countable', $display);
        $this->assertStringContainsString('! [NOTE] Unable to get message count for the following transports: "simple".', $display);
    }

    public function testWithNotExistingTransport()
    {
        $tester = new CommandTester($this->command);
        $tester->execute(['transport_names' => ['unexisting']]);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('[WARNING] The "unexisting" transport does not exist.', $display);
        $this->assertStringNotContainsString('message_countable', $display);
        $this->assertStringNotContainsString('another_message_countable', $display);
        $this->assertStringNotContainsString('! [NOTE] Unable to get message count for the following transports: "simple".', $display);
    }
}
