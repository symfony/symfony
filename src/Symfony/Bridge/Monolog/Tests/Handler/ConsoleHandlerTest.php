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

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\BufferedOutput;
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
        $this->assertFalse($handler->isHandling([]), '->isHandling returns false when no output is set');
    }

    /**
     * @dataProvider provideVerbosityMappingTests
     */
    public function testVerbosityMapping($verbosity, $level, $isHandling, array $map = [])
    {
        $output = $this->getMockBuilder('Symfony\Component\Console\Output\OutputInterface')->getMock();
        $output
            ->expects($this->atLeastOnce())
            ->method('getVerbosity')
            ->willReturn($verbosity)
        ;
        $handler = new ConsoleHandler($output, true, $map);
        $this->assertSame($isHandling, $handler->isHandling(['level' => $level]),
            '->isHandling returns correct value depending on console verbosity and log level'
        );

        // check that the handler actually outputs the record if it handles it
        $levelName = Logger::getLevelName($level);
        $levelName = sprintf('%-9s', $levelName);

        $realOutput = $this->getMockBuilder('Symfony\Component\Console\Output\Output')->setMethods(['doWrite'])->getMock();
        $realOutput->setVerbosity($verbosity);
        if ($realOutput->isDebug()) {
            $log = "16:21:54 $levelName [app] My info message\n";
        } else {
            $log = "16:21:54 $levelName [app] My info message\n";
        }
        $realOutput
            ->expects($isHandling ? $this->once() : $this->never())
            ->method('doWrite')
            ->with($log, false);
        $handler = new ConsoleHandler($realOutput, true, $map);

        $infoRecord = [
            'message' => 'My info message',
            'context' => [],
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'app',
            'datetime' => new \DateTime('2013-05-29 16:21:54'),
            'extra' => [],
        ];
        $this->assertFalse($handler->handle($infoRecord), 'The handler finished handling the log.');
    }

    public function provideVerbosityMappingTests()
    {
        return [
            [OutputInterface::VERBOSITY_QUIET, Logger::ERROR, true],
            [OutputInterface::VERBOSITY_QUIET, Logger::WARNING, false],
            [OutputInterface::VERBOSITY_NORMAL, Logger::WARNING, true],
            [OutputInterface::VERBOSITY_NORMAL, Logger::NOTICE, false],
            [OutputInterface::VERBOSITY_VERBOSE, Logger::NOTICE, true],
            [OutputInterface::VERBOSITY_VERBOSE, Logger::INFO, false],
            [OutputInterface::VERBOSITY_VERY_VERBOSE, Logger::INFO, true],
            [OutputInterface::VERBOSITY_VERY_VERBOSE, Logger::DEBUG, false],
            [OutputInterface::VERBOSITY_DEBUG, Logger::DEBUG, true],
            [OutputInterface::VERBOSITY_DEBUG, Logger::EMERGENCY, true],
            [OutputInterface::VERBOSITY_NORMAL, Logger::NOTICE, true, [
                OutputInterface::VERBOSITY_NORMAL => Logger::NOTICE,
            ]],
            [OutputInterface::VERBOSITY_DEBUG, Logger::NOTICE, true, [
                OutputInterface::VERBOSITY_NORMAL => Logger::NOTICE,
            ]],
        ];
    }

    public function testVerbosityChanged()
    {
        $output = $this->getMockBuilder('Symfony\Component\Console\Output\OutputInterface')->getMock();
        $output
            ->expects($this->at(0))
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_QUIET)
        ;
        $output
            ->expects($this->at(1))
            ->method('getVerbosity')
            ->willReturn(OutputInterface::VERBOSITY_DEBUG)
        ;
        $handler = new ConsoleHandler($output);
        $this->assertFalse($handler->isHandling(['level' => Logger::NOTICE]),
            'when verbosity is set to quiet, the handler does not handle the log'
        );
        $this->assertTrue($handler->isHandling(['level' => Logger::NOTICE]),
            'since the verbosity of the output increased externally, the handler is now handling the log'
        );
    }

    public function testGetFormatter()
    {
        $handler = new ConsoleHandler();
        $this->assertInstanceOf('Symfony\Bridge\Monolog\Formatter\ConsoleFormatter', $handler->getFormatter(),
            '-getFormatter returns ConsoleFormatter by default'
        );
    }

    public function testWritingAndFormatting()
    {
        $output = $this->getMockBuilder('Symfony\Component\Console\Output\OutputInterface')->getMock();
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

        $infoRecord = [
            'message' => 'My info message',
            'context' => [],
            'level' => Logger::INFO,
            'level_name' => Logger::getLevelName(Logger::INFO),
            'channel' => 'app',
            'datetime' => new \DateTime('2013-05-29 16:21:54'),
            'extra' => [],
        ];

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
            $logger->addInfo('Before command message.');
        });
        $dispatcher->addListener(ConsoleEvents::TERMINATE, function () use ($logger) {
            $logger->addInfo('Before terminate message.');
        });

        $dispatcher->addSubscriber($handler);

        $dispatcher->addListener(ConsoleEvents::COMMAND, function () use ($logger) {
            $logger->addInfo('After command message.');
        });
        $dispatcher->addListener(ConsoleEvents::TERMINATE, function () use ($logger) {
            $logger->addInfo('After terminate message.');
        });

        $event = new ConsoleCommandEvent(new Command('foo'), $this->getMockBuilder('Symfony\Component\Console\Input\InputInterface')->getMock(), $output);
        $dispatcher->dispatch(ConsoleEvents::COMMAND, $event);
        $this->assertStringContainsString('Before command message.', $out = $output->fetch());
        $this->assertStringContainsString('After command message.', $out);

        $event = new ConsoleTerminateEvent(new Command('foo'), $this->getMockBuilder('Symfony\Component\Console\Input\InputInterface')->getMock(), $output, 0);
        $dispatcher->dispatch(ConsoleEvents::TERMINATE, $event);
        $this->assertStringContainsString('Before terminate message.', $out = $output->fetch());
        $this->assertStringContainsString('After terminate message.', $out);
    }
}
