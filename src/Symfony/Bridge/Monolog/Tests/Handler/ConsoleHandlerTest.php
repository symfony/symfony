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
        // The VERBOSITY_SILENT const is not defined for Console below 7.2, but in that case, the code behaves as before
        if (!\defined('\Symfony\Component\Console\Output\OutputInterface::VERBOSITY_SILENT')) {
            return [
                [OutputInterface::VERBOSITY_NORMAL, Level::Warning, true, true],
                [OutputInterface::VERBOSITY_NORMAL, Level::Info, false, false],
            ];
        }

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
            ->expects($this->any())
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
        $dispatcher->addListener(ConsoleEvents::COMMAND, function () use ($logger) {
            $logger->info('Before command message.');
        });
        $dispatcher->addListener(ConsoleEvents::TERMINATE, function () use ($logger) {
            $logger->info('Before terminate message.');
        });

        $dispatcher->addSubscriber($handler);

        $dispatcher->addListener(ConsoleEvents::COMMAND, function () use ($logger) {
            $logger->info('After command message.');
        });
        $dispatcher->addListener(ConsoleEvents::TERMINATE, function () use ($logger) {
            $logger->info('After terminate message.');
        });

        $event = new ConsoleCommandEvent(new Command('foo'), $this->createMock(InputInterface::class), $output);
        $dispatcher->dispatch($event, ConsoleEvents::COMMAND);
        $this->assertStringContainsString('Before command message.', $out = $output->fetch());
        $this->assertStringContainsString('After command message.', $out);

        $event = new ConsoleTerminateEvent(new Command('foo'), $this->createMock(InputInterface::class), $output, 0);
        $dispatcher->dispatch($event, ConsoleEvents::TERMINATE);
        $this->assertStringContainsString('Before terminate message.', $out = $output->fetch());
        $this->assertStringContainsString('After terminate message.', $out);
    }

    public function testInteractiveOnly()
    {
        $output = $this->createMock(OutputInterface::class);

        $message = RecordFactory::create(Level::Info, 'My info message');
        $interactiveInput = $this->createMock(InputInterface::class);
        $interactiveInput
            ->method('isInteractive')
            ->willReturn(true);
        $handler = new ConsoleHandler(interactiveOnly: true);
        $handler->setInput($interactiveInput);
        $handler->setOutput($output);
        self::assertTrue($handler->isHandling($message), '->isHandling returns true when input is interactive');
        self::assertFalse($handler->getBubble(), '->getBubble returns false when input is interactive and interactiveOnly is true');

        $nonInteractiveInput = $this->createMock(InputInterface::class);
        $nonInteractiveInput
            ->method('isInteractive')
            ->willReturn(false);
        $handler = new ConsoleHandler(interactiveOnly: true);
        $handler->setInput($nonInteractiveInput);
        $handler->setOutput($output);
        self::assertFalse($handler->isHandling($message), '->isHandling returns false when input is not interactive');
        self::assertTrue($handler->getBubble(), '->getBubble returns true when input is not interactive and interactiveOnly is true');
    }
}
