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
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests the ConsoleHandler and also the ConsoleFormatter.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class ConsoleHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $handler = new ConsoleHandler(null, false);
        $this->assertFalse($handler->getBubble(), 'the bubble parameter gets propagated');
    }

    public function testIsHandling()
    {
        $handler = new ConsoleHandler();
        $this->assertFalse($handler->isHandling(array()), '->isHandling returns false when no output is set');
    }

    /**
     * @dataProvider provideVerbosityMappingTests
     */
    public function testVerbosityMapping($verbosity, $level, $isHandling, array $map = array())
    {
        $output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
        $output
            ->expects($this->atLeastOnce())
            ->method('getVerbosity')
            ->will($this->returnValue($verbosity))
        ;
        $handler = new ConsoleHandler($output, true, $map);
        $this->assertSame($isHandling, $handler->isHandling(array('level' => $level)),
            '->isHandling returns correct value depending on console verbosity and log level'
        );
    }

    public function provideVerbosityMappingTests()
    {
        return array(
            array(OutputInterface::VERBOSITY_QUIET, Logger::ERROR, false),
            array(OutputInterface::VERBOSITY_NORMAL, Logger::WARNING, true),
            array(OutputInterface::VERBOSITY_NORMAL, Logger::NOTICE, false),
            array(OutputInterface::VERBOSITY_VERBOSE, Logger::NOTICE, true),
            array(OutputInterface::VERBOSITY_VERBOSE, Logger::INFO, false),
            array(OutputInterface::VERBOSITY_VERY_VERBOSE, Logger::INFO, true),
            array(OutputInterface::VERBOSITY_VERY_VERBOSE, Logger::DEBUG, false),
            array(OutputInterface::VERBOSITY_DEBUG, Logger::DEBUG, true),
            array(OutputInterface::VERBOSITY_DEBUG, Logger::EMERGENCY, true),
            array(OutputInterface::VERBOSITY_NORMAL, Logger::NOTICE, true, array(
                OutputInterface::VERBOSITY_NORMAL => Logger::NOTICE
            )),
            array(OutputInterface::VERBOSITY_DEBUG, Logger::NOTICE, true, array(
                OutputInterface::VERBOSITY_NORMAL => Logger::NOTICE
            )),
        );
    }

    public function testVerbosityChanged()
    {
        $output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
        $output
            ->expects($this->at(0))
            ->method('getVerbosity')
            ->will($this->returnValue(OutputInterface::VERBOSITY_QUIET))
        ;
        $output
            ->expects($this->at(1))
            ->method('getVerbosity')
            ->will($this->returnValue(OutputInterface::VERBOSITY_DEBUG))
        ;
        $handler = new ConsoleHandler($output);
        $this->assertFalse($handler->isHandling(array('level' => Logger::NOTICE)),
            'when verbosity is set to quiet, the handler does not handle the log'
        );
        $this->assertTrue($handler->isHandling(array('level' => Logger::NOTICE)),
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
        $output = $this->getMock('Symfony\Component\Console\Output\ConsoleOutputInterface');
        $output
            ->expects($this->any())
            ->method('getVerbosity')
            ->will($this->returnValue(OutputInterface::VERBOSITY_DEBUG))
        ;
        $output
            ->expects($this->once())
            ->method('write')
            ->with('<info>[2013-05-29 16:21:54] app.INFO:</info> My info message [] []'."\n")
        ;

        $errorOutput = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
        $errorOutput
            ->expects($this->once())
            ->method('write')
            ->with('<error>[2013-05-29 16:21:54] app.ERROR:</error> My error message [] []'."\n")
        ;

        $output
            ->expects($this->any())
            ->method('getErrorOutput')
            ->will($this->returnValue($errorOutput))
        ;

        $handler = new ConsoleHandler(null, false);
        $handler->setOutput($output);

        $infoRecord = array(
            'message' => 'My info message',
            'context' => array(),
            'level' => Logger::INFO,
            'level_name' => Logger::getLevelName(Logger::INFO),
            'channel' => 'app',
            'datetime' => new \DateTime('2013-05-29 16:21:54'),
            'extra' => array(),
        );

        $this->assertTrue($handler->handle($infoRecord), 'The handler finished handling the log as bubble is false.');

        $errorRecord = array(
            'message' => 'My error message',
            'context' => array(),
            'level' => Logger::ERROR,
            'level_name' => Logger::getLevelName(Logger::ERROR),
            'channel' => 'app',
            'datetime' => new \DateTime('2013-05-29 16:21:54'),
            'extra' => array(),
        );

        $this->assertTrue($handler->handle($errorRecord), 'The handler finished handling the log as bubble is false.');
    }
}
