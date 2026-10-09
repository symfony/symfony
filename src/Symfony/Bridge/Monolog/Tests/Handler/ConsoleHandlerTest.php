<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Handler;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests the ConsoleHandler and also the ConsoleFormatter.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class ConsoleHandlerTest extends TestCase
{
    public function testConstructor()
    {
        $handler = new ConsoleHandler(null, false);
        $this->assertFalse($handler->getBubble(), 'the bubble parameter gets propagated');
    }

    public function testIsHandling()
    {
        $handler = new ConsoleHandler();
        $this->assertFalse($handler->isHandling(RecordFactory::create()), '->isHandling returns false when no output is set');
    }

    #[DataProvider('provideVerbosityMappingTests')]
    public function testVerbosityMapping($verbosity, $level, $isHandling, array $map = [])
    {
        $output = $this->createMock(OutputInterface::class);
        $output
            ->expects($this->atLeastOnce())
            ->method('getVerbosity')
            ->willReturn($verbosity)
        ;
        $handler = new ConsoleHandler($output, true, $map);
        $this->assertSame($isHandling, $handler->isHandling(RecordFactory::create($level)),
            '->isHandling returns correct value depending on console verbosity and log level'
        );

        // check that the handler actually outputs the record if it handles it
        $levelName = Logger::getLevelName($level);
        $levelName = \sprintf('%-9s', $levelName);

        $realOutput = $this->getMockBuilder(Output::class)->onlyMethods(['doWrite'])->getMock();
        $realOutput->setVerbosity($verbosity);
        $log = "16:21:54 $levelName [app] My info message\n";
        $realOutput
            ->expects($isHandling ? $this->once() : $this->never())
            ->method('doWrite')
            ->with($log, false);
        $handler = new ConsoleHandler($realOutput, true, $map);

        $infoRecord = RecordFactory::create($level, 'My info message', 'app', datetime: new \DateTimeImmutable('2013-05-29 16:21:54'));
        $this->assertFalse($handler->handle($infoRecord), 'The handler finished handling the log.');
    }

    public static function provideVerbosityMappingTests(): array
    {
        return [
            [OutputInterface::VERBOSITY_QUIET, Level::Error, true],
            [OutputInterface::VERBOSITY_QUIET, Level::Warning, false],
            [OutputInterface::VERBOSITY_NORMAL, Level::Warning, true],
            [OutputInterface::VERBOSITY_NORMAL, Level::Notice, false],
            [OutputInterface::VERBOSITY_VERBOSE, Level::Notice, true],
            [OutputInterface::VERBOSITY_VERBOSE, Level::Info, false],
            [OutputInterface::VERBOSITY_VERY_VERBOSE, Level::Info, true],
            [OutputInterface::VERBOSITY_VERY_VERBOSE, Level::Debug, false],
            [OutputInterface::VERBOSITY_DEBUG, Level::Debug, true],
            [OutputInterface::VERBOSITY_DEBUG, Level::Emergency, true],
            [OutputInterface::VERBOSITY_NORMAL, Level::Notice, true, [
                OutputInterface::VERBOSITY_NORMAL => Level::Notice,
            ]],
            [OutputInterface::VERBOSITY_DEBUG, Level::Notice, true, [
                OutputInterface::VERBOSITY_NORMAL => Level::Notice,
            ]],
        ];
    }

    #[DataProvider('provideHandleOrBubbleSilentTests')]
    public function testHandleOrBubbleSilent(int $verbosity, Level $level, bool $isHandling, bool $isWriting, array $map = [])
    {
        $output = $this->createMock(OutputInterface::class);
        $output
            ->expects($this->atLeastOnce())
            ->method('getVerbosity')
            ->willReturn($verbosity)
        ;
        $handler = new ConsoleHandler($output, false, $map);
        $this->assertSame($isHandling, $handler->isHandling(RecordFactory::create($level)), '->isHandling returns correct value depending on console verbosity and log level');

        // check that the handler actually outputs the record if it handles it at verbosity above SILENT
        $levelName = Logger::getLevelName($level);
        $levelName = \sprintf('%-9s', $levelName);

        $realOutput = $this->getMockBuilder(Output::class)->onlyMethods(['doWrite'])->getMock();
        $realOutput->setVerbosity($verbosity);
        $log = "16:21:54 $levelName [app] My info message\n";
        $realOutput
            ->expects($isWriting ? $this->once() : $this->never())
            ->method('doWrite')
            ->with($log, false);
        $handler = new ConsoleHandler($realOutput, false, $map);

        $infoRecord = RecordFactory::create($level, 'My info message', 'app', datetime: new \DateTimeImmutable('2013-05-29 16:21:54'));
        $this->assertSame($isHandling, $handler->handle($infoRecord), 'The handler bubbled correctly when it did not output the message.');
    }

    public static function provideHandleOrBubbleSilentTests(): array
    {
        return [
            [OutputInterface::VERBOSITY_SILENT, Level::Warning, false, false],
            [OutputInterface::VERBOSITY_NORMAL, Level::Warning, true, true],
            [OutputInterface::VERBOSITY_NORMAL, Level::Info, false, false],
            [OutputInterface::VERBOSITY_SILENT, Level::Warning, true, false, [OutputInterface::VERBOSITY_SILENT => Level::Warning]],
            [OutputInterface::VERBOSITY_SILENT, Level::Warning, false, false, [OutputInterface::VERBOSITY_SILENT => Level::Error]],
            [OutputInterface::VERBOSITY_SILENT, Level::Emergency, false, false],
            [OutputInterface::VERBOSITY_SILENT, Level::Emergency, true, false, [OutputInterface::VERBOSITY_SILENT => Level::Emergency]],
        ];
    }

    public function testVerbosityChanged()
    {
        $output = $this->createMock(OutputInterface::class);
        $output
            ->expects($this->exactly(2))
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_QUIET, OutputInterface::VERBOSITY_DEBUG)
        ;
        $handler = new ConsoleHandler($output);
        $this->assertFalse($handler->isHandling(RecordFactory::create(Level::Notice)),
            'when verbosity is set to quiet, the handler does not handle the log'
        );
        $this->assertTrue($handler->isHandling(RecordFactory::create(Level::Notice)),
            'since the verbosity of the output increased externally, the handler is now handling the log'
        );
    }

    public function testGetFormatter()
    {
        $handler = new ConsoleHandler();
        $this->assertInstanceOf(
            ConsoleFormatter::class, $handler->getFormatter(),
            '->getFormatter returns ConsoleFormatter by default'
        );
    }

    public function testWritingAndFormatting()
    {
        $output = $this->createMock(OutputInterface::class);
        $output
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_DEBUG)
        ;
        $output
            ->expects($this->once())
            ->method('write')
            ->with("16:21:54 <fg=green>INFO     </> <comment>[app]</> My info message\n")
        ;

        $handler = new ConsoleHandler(null, false);
        $handler->setOutput($output);

        $infoRecord = RecordFactory::create(Level::Info, 'My info message', 'app', datetime: new \DateTimeImmutable('2013-05-29 16:21:54'));

        $this->assertTrue($handler->handle($infoRecord), 'The handler finished handling the log as bubble is false.');
    }

    public function testLogsFromListeners()
    {
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $handler = new ConsoleHandler(null, false);

        $logger = new Logger('app');
        $logger->pushHandler($handler);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ConsoleEvents::COMMAND, static function () use ($logger) {
            $logger->info('Before command message.');
        });
        $dispatcher->addListener(ConsoleEvents::TERMINATE, static function () use ($logger) {
            $logger->info('Before terminate message.');
        });

        $dispatcher->addSubscriber($handler);

        $dispatcher->addListener(ConsoleEvents::COMMAND, static function () use ($logger) {
            $logger->info('After command message.');
        });
        $dispatcher->addListener(ConsoleEvents::TERMINATE, static function () use ($logger) {
            $logger->info('After terminate message.');
        });

        $event = new ConsoleCommandEvent(new Command('foo'), new ArrayInput([]), $output);
        $dispatcher->dispatch($event, ConsoleEvents::COMMAND);
        $this->assertStringContainsString('Before command message.', $out = $output->fetch());
        $this->assertStringContainsString('After command message.', $out);

        $event = new ConsoleTerminateEvent(new Command('foo'), new ArrayInput([]), $output, 0);
        $dispatcher->dispatch($event, ConsoleEvents::TERMINATE);
        $this->assertStringContainsString('Before terminate message.', $out = $output->fetch());
        $this->assertStringContainsString('After terminate message.', $out);
    }

    public function testInteractiveOnly()
    {
        $output = $this->createStub(OutputInterface::class);

        $message = RecordFactory::create(Level::Info, 'My info message');
        $interactiveInput = $this->createStub(InputInterface::class);
        $interactiveInput
            ->method('isInteractive')
            ->willReturn(true);
        $handler = new ConsoleHandler(interactiveOnly: true);
        $handler->setInput($interactiveInput);
        $handler->setOutput($output);
        self::assertTrue($handler->isHandling($message), '->isHandling returns true when input is interactive');
        self::assertFalse($handler->getBubble(), '->getBubble returns false when input is interactive and interactiveOnly is true');

        $nonInteractiveInput = $this->createStub(InputInterface::class);
        $nonInteractiveInput
            ->method('isInteractive')
            ->willReturn(false);
        $handler = new ConsoleHandler(interactiveOnly: true);
        $handler->setInput($nonInteractiveInput);
        $handler->setOutput($output);
        self::assertFalse($handler->isHandling($message), '->isHandling returns false when input is not interactive');
        self::assertTrue($handler->getBubble(), '->getBubble returns true when input is not interactive and interactiveOnly is true');
    }

    public function testInteractiveOnlyPreventsPropagationWhenInteractive()
    {
        $interactiveInput = $this->createStub(InputInterface::class);
        $interactiveInput->method('isInteractive')->willReturn(true);

        $consoleHandler = new ConsoleHandler(null, true, [], [], true);
        $consoleHandler->setInput($interactiveInput);
        $consoleHandler->setOutput(new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE));

        $sibling = new TestHandler();
        $logger = new Logger('test', [$consoleHandler, $sibling]);

        $logger->warning('hello');

        self::assertFalse(
            $sibling->hasWarningRecords(),
            'sibling handler must not receive records when interactive_only is true and input is interactive',
        );
    }

    public function testInteractiveOnlyAllowsPropagationWhenNotInteractive()
    {
        $nonInteractiveInput = $this->createStub(InputInterface::class);
        $nonInteractiveInput->method('isInteractive')->willReturn(false);

        $consoleHandler = new ConsoleHandler(null, true, [], [], true);
        $consoleHandler->setInput($nonInteractiveInput);
        $consoleHandler->setOutput(new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE));

        $sibling = new TestHandler();
        $logger = new Logger('test', [$consoleHandler, $sibling]);

        $logger->warning('hello');

        self::assertTrue(
            $sibling->hasWarningRecords(),
            'sibling handler must still receive records when interactive_only is true but input is not interactive',
        );
    }

    public function testNestedCommandsDoNotCloseHandler()
    {
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $handler = new ConsoleHandler(null, false);

        $logger = new Logger('app');
        $logger->pushHandler($handler);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($handler);

        $parentInput = new ArrayInput([]);
        $subInput = new ArrayInput([]);

        $dispatcher->dispatch(new ConsoleCommandEvent(new Command('messenger:consume'), $parentInput, $output), ConsoleEvents::COMMAND);
        $logger->info('log from parent');
        $this->assertStringContainsString('log from parent', $output->fetch());

        $subOutput = new BufferedOutput();
        $dispatcher->dispatch(new ConsoleCommandEvent(new Command('nested:task'), $subInput, $subOutput), ConsoleEvents::COMMAND);
        $dispatcher->dispatch(new ConsoleTerminateEvent(new Command('nested:task'), $subInput, $subOutput, Command::SUCCESS), ConsoleEvents::TERMINATE);

        $logger->info('log after sub-command');
        $this->assertStringContainsString('log after sub-command', $output->fetch(), 'Handler must still be active after nested command terminates');

        // Parent command terminates: handler must be closed now
        $dispatcher->dispatch(new ConsoleTerminateEvent(new Command('messenger:consume'), $parentInput, $output, Command::SUCCESS), ConsoleEvents::TERMINATE);
        $this->assertFalse($handler->isHandling(RecordFactory::create(Logger::DEBUG)), 'Handler must be closed after main command terminates');
    }

    #[DataProvider('provideChannelFilters')]
    public function testChannelFiltering(array $channels, string $channel, bool $isHandling)
    {
        $handler = self::createHandler($channels, new BufferedOutput(OutputInterface::VERBOSITY_DEBUG));

        $this->assertSame($isHandling, $handler->isHandling(RecordFactory::create(Level::Info, 'My info message', $channel)),
            'SYMFONY_CONSOLE_LOG_CHANNELS selects the channels written to the console'
        );
    }

    public static function provideChannelFilters(): iterable
    {
        yield 'no channel selected' => [[], 'app', true];
        yield 'the channel is included' => [['app'], 'app', true];
        yield 'another channel is included' => [['app'], 'doctrine', false];
        yield 'the channel is one of the included ones' => [['app', 'doctrine'], 'doctrine', true];
        yield 'the channel is excluded' => [['!event'], 'event', false];
        yield 'another channel is excluded' => [['!event'], 'app', true];
        yield 'the channel is one of the excluded ones' => [['!event', '!php'], 'php', false];
    }

    #[DataProvider('provideCombinedChannels')]
    public function testIncludedAndExcludedChannelsCannotBeCombined(array $channels)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot combine included and excluded channels in "SYMFONY_CONSOLE_LOG_CHANNELS".');

        self::createHandler($channels, new BufferedOutput(OutputInterface::VERBOSITY_DEBUG));
    }

    public static function provideCombinedChannels(): iterable
    {
        yield 'an excluded channel after an included one' => [['app', '!event']];
        yield 'an included channel after an excluded one' => [['!event', 'app']];
    }

    public function testChannelsAreNotReadWhenTheHandlerIsBuilt()
    {
        $env = $_ENV;
        $_ENV['SYMFONY_CONSOLE_LOG_CHANNELS'] = '!app';

        try {
            $handler = new ConsoleHandler(new BufferedOutput(OutputInterface::VERBOSITY_DEBUG));
        } finally {
            $_ENV = $env;
        }

        $this->assertTrue($handler->isHandling(RecordFactory::create(Level::Info, 'My info message', 'app')),
            'the channels are only read when a command runs, so building the logger for a web request cannot fail on them'
        );
    }

    public function testChannelFilteringAllowsPropagation()
    {
        $consoleHandler = self::createHandler(['app'], new BufferedOutput(OutputInterface::VERBOSITY_DEBUG), false);

        $sibling = new TestHandler();
        $logger = new Logger('doctrine', [$consoleHandler, $sibling]);

        $logger->warning('hello');

        self::assertTrue(
            $sibling->hasWarningRecords(),
            'sibling handler must still receive the records of the channels filtered out of the console',
        );
    }

    private static function createHandler(array $channels, OutputInterface $output, bool $bubble = true): ConsoleHandler
    {
        $env = $_ENV;
        $_ENV['SYMFONY_CONSOLE_LOG_CHANNELS'] = implode(', ', $channels);

        try {
            $handler = new ConsoleHandler(null, $bubble);
            $handler->onCommand(new ConsoleCommandEvent(new Command('foo'), new ArrayInput([]), $output));

            return $handler;
        } finally {
            $_ENV = $env;
        }
    }
}
