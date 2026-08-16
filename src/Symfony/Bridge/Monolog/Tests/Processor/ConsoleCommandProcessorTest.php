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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

    public function testCommandDataSurvivesReset()
    {
        $processor = new ConsoleCommandProcessor();
        $processor->addCommandData($this->getConsoleEvent());

        $processor->reset();

        $record = $processor(RecordFactory::create());

        $this->assertArrayHasKey('command', $record['extra']);
        $this->assertSame(self::TEST_NAME, $record['extra']['command']['name']);
    }

    public function testCommandDataIsRemovedWhenCommandTerminates()
    {
        $processor = new ConsoleCommandProcessor();
        $processor->addCommandData($this->getConsoleEvent());

        $processor->removeCommandData();

        $record = $processor(RecordFactory::create());
        $this->assertEquals([], $record['extra']);

        $processor->removeCommandData();

        $record = $processor(RecordFactory::create());
        $this->assertEquals([], $record['extra']);
    }

    public function testCommandDataIsRestoredWhenNestedCommandTerminates()
    {
        $processor = new ConsoleCommandProcessor();
        $processor->addCommandData($this->getConsoleEvent());
        $processor->addCommandData($this->getConsoleEvent('some:nested'));

        $record = $processor(RecordFactory::create());
        $this->assertSame('some:nested', $record['extra']['command']['name']);

        $processor->removeCommandData();

        $record = $processor(RecordFactory::create());
        $this->assertSame(self::TEST_NAME, $record['extra']['command']['name']);
    }

    public function testCommandDataOfNestedCommandSurvivesReset()
    {
        $processor = new ConsoleCommandProcessor();
        $processor->addCommandData($this->getConsoleEvent());
        $processor->addCommandData($this->getConsoleEvent('some:nested'));

        $processor->reset();

        $record = $processor(RecordFactory::create());
        $this->assertSame('some:nested', $record['extra']['command']['name']);
    }

    public function testCommandDataFollowsANestedCommandRun()
    {
        $processor = new ConsoleCommandProcessor();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($processor);

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($dispatcher);

        $records = [];
        $capture = static function (string $key) use ($processor, &$records) {
            $records[$key] = $processor(RecordFactory::create());
        };

        $dispatcher->addListener(ConsoleEvents::TERMINATE, static function (ConsoleTerminateEvent $event) use ($capture) {
            $capture('terminate of '.$event->getCommand()->getName());
        }, -255);

        $nested = new Command('some:nested');
        $nested->setCode(static function () use ($capture) {
            $capture('inside the nested command');

            return 0;
        });

        $outer = new Command(self::TEST_NAME);
        $outer->setCode(static function () use ($application, $capture) {
            $capture('before the nested command');
            $application->run(new ArrayInput(['command' => 'some:nested']), new NullOutput());
            $capture('after the nested command');

            return 0;
        });

        $application->addCommands([$nested, $outer]);
        $application->run(new ArrayInput(['command' => self::TEST_NAME]), new NullOutput());
        $capture('after the outer command');

        $this->assertSame(self::TEST_NAME, $records['before the nested command']['extra']['command']['name']);
        $this->assertSame('some:nested', $records['inside the nested command']['extra']['command']['name']);
        $this->assertSame('some:nested', $records['terminate of some:nested']['extra']['command']['name']);
        $this->assertSame(self::TEST_NAME, $records['after the nested command']['extra']['command']['name']);
        $this->assertSame(self::TEST_NAME, $records['terminate of '.self::TEST_NAME]['extra']['command']['name']);
        $this->assertSame([], $records['after the outer command']['extra']);
    }

    private function getConsoleEvent(string $name = self::TEST_NAME): ConsoleEvent
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArguments')->willReturn(self::TEST_ARGUMENTS);
        $input->method('getOptions')->willReturn(self::TEST_OPTIONS);
        $command = new Command($name);

        return new ConsoleEvent($command, $input, new NullOutput());
    }
}
