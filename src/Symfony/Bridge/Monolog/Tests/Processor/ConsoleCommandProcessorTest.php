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
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleCommandProcessorTest extends TestCase
{
    private const TEST_ARGUMENTS = ['test' => 'argument'];
    private const TEST_OPTIONS = ['test' => 'option'];
    private const TEST_NAME = 'some:test';

    public function testProcessor()
    {
        $processor = new ConsoleCommandProcessor();
        $processor->addCommandData($this->getConsoleEvent());

        $record = $processor(RecordFactory::create());

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

        $record = $processor(RecordFactory::create());

        $this->assertArrayHasKey('command', $record['extra']);
        $this->assertEquals(
            ['name' => self::TEST_NAME, 'arguments' => self::TEST_ARGUMENTS, 'options' => self::TEST_OPTIONS],
            $record['extra']['command']
        );
    }

    public function testProcessorDoesNothingWhenNotInConsole()
    {
        $processor = new ConsoleCommandProcessor(true, true);

        $record = $processor(RecordFactory::create());
        $this->assertEquals([], $record['extra']);
    }

    private function getConsoleEvent(): ConsoleEvent
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getArguments')->willReturn(self::TEST_ARGUMENTS);
        $input->method('getOptions')->willReturn(self::TEST_OPTIONS);
        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn(self::TEST_NAME);

        return new ConsoleEvent($command, $input, $this->createMock(OutputInterface::class));
    }
}
