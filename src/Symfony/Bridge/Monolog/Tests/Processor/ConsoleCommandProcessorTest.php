<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Processor;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\ConsoleCommandProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Input\InputInterface;

class ConsoleCommandProcessorTest extends TestCase
{
    private const TEST_ARGUMENTS = ['test' => 'argument'];
    private const TEST_OPTIONS = ['test' => 'option'];
    private const TEST_NAME = 'some:test';

    public function testProcessor()
    {
        $processor = new ConsoleCommandProcessor();
        $processor->addCommandData($this->getConsoleEvent());

        $record = $processor(['extra' => []]);

        $this->assertArrayHasKey('command', $record['extra']);
        $this->assertEquals(
            ['name' => self::TEST_NAME, 'arguments' => self::TEST_ARGUMENTS],
            $record['extra']['command']
        );
    }

    public function testProcessorWithOptions()
    {
        $processor = new ConsoleCommandProcessor(true, true);
        $processor->addCommandData($this->getConsoleEvent());

        $record = $processor(['extra' => []]);

        $this->assertArrayHasKey('command', $record['extra']);
        $this->assertEquals(
            ['name' => self::TEST_NAME, 'arguments' => self::TEST_ARGUMENTS, 'options' => self::TEST_OPTIONS],
            $record['extra']['command']
        );
    }

    public function testProcessorDoesNothingWhenNotInConsole()
    {
        $processor = new ConsoleCommandProcessor(true, true);

        $record = $processor(['extra' => []]);
        $this->assertEquals(['extra' => []], $record);
    }

    private function getConsoleEvent(): ConsoleEvent
    {
        $input = $this->getMockBuilder(InputInterface::class)->getMock();
        $input->method('getArguments')->willReturn(self::TEST_ARGUMENTS);
        $input->method('getOptions')->willReturn(self::TEST_OPTIONS);
        $command = $this->getMockBuilder(Command::class)->disableOriginalConstructor()->getMock();
        $command->method('getName')->willReturn(self::TEST_NAME);
        $consoleEvent = $this->getMockBuilder(ConsoleEvent::class)->disableOriginalConstructor()->getMock();
        $consoleEvent->method('getCommand')->willReturn($command);
        $consoleEvent->method('getInput')->willReturn($input);

        return $consoleEvent;
    }
}
