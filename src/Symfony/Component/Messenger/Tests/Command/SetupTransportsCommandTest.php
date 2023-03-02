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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Command\SetupTransportsCommand;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class SetupTransportsCommandTest extends TestCase
{
    public function testReceiverNames()
    {
        // mock a service locator
        /** @var MockObject&ServiceLocator $serviceLocator */
        $serviceLocator = $this->createMock(ServiceLocator::class);
        // get method must be call twice and will return consecutively a setup-able transport and a non setup-able transport
        $serviceLocator->expects($this->exactly(2))
            ->method('get')
            ->will($this->onConsecutiveCalls(
                $this->createMock(SetupableTransportInterface::class),
                $this->createMock(TransportInterface::class)
            ));
        $serviceLocator
            ->method('has')
            ->willReturn(true);

        $command = new SetupTransportsCommand($serviceLocator, ['amqp', 'other_transport']);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('The "amqp" transport was set up successfully.', $display);
        $this->assertStringContainsString('The "other_transport" transport does not support setup.', $display);
    }

    public function testReceiverNameArgument()
    {
        // mock a service locator
        /** @var MockObject&ServiceLocator $serviceLocator */
        $serviceLocator = $this->createMock(ServiceLocator::class);
        // get method must be call twice and will return consecutively a setup-able transport and a non setup-able transport
        $serviceLocator->expects($this->exactly(1))
            ->method('get')
            ->will($this->onConsecutiveCalls(
                $this->createMock(SetupableTransportInterface::class)
            ));
        $serviceLocator->expects($this->exactly(1))
            ->method('has')
            ->willReturn(true);

        $command = new SetupTransportsCommand($serviceLocator, ['amqp', 'other_transport']);
        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'amqp']);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('The "amqp" transport was set up successfully.', $display);
    }

    public function testReceiverNameArgumentNotFound()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "not_found" transport does not exist.');
        // mock a service locator
        /** @var MockObject&ServiceLocator $serviceLocator */
        $serviceLocator = $this->createMock(ServiceLocator::class);
        // get method must be call twice and will return consecutively a setup-able transport and a non setup-able transport
        $serviceLocator->expects($this->exactly(0))
            ->method('get');
        $serviceLocator->expects($this->exactly(1))
            ->method('has')
            ->willReturn(false);

        $command = new SetupTransportsCommand($serviceLocator, ['amqp', 'other_transport']);
        $tester = new CommandTester($command);
        $tester->execute(['transport' => 'not_found']);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $command = new SetupTransportsCommand($serviceLocator, ['amqp', 'other_transport']);
        $tester = new CommandCompletionTester($command);
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions()
    {
        yield 'transport' => [[''], ['amqp', 'other_transport']];
    }
}
