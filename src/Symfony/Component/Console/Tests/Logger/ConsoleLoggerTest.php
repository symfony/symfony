<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Logger;

use Psr\Log\Test\LoggerInterfaceTest;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tests\Fixtures\DummyOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console logger test.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ConsoleLoggerTest extends LoggerInterfaceTest
{
    /**
     * @var DummyOutput
     */
    protected $output;

    /**
     * {@inheritdoc}
     */
    public function getLogger()
    {
        $this->output = new DummyOutput(OutputInterface::VERBOSITY_VERBOSE);

        return new ConsoleLogger($this->output, array(
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_NORMAL,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        return $this->output->getLogs();
    }

    /**
     * @dataProvider provideOutputMappingParams
     */
    public function testOutputMapping($logLevel, $outputVerbosity, $isOutput, $addVerbosityLevelMap = array())
    {
        $out = new BufferedOutput($outputVerbosity);
        $logger = new ConsoleLogger($out, $addVerbosityLevelMap);
        $logger->log($logLevel, 'foo bar');
        $logs = $out->fetch();
        $this->assertEquals($isOutput ? "[$logLevel] foo bar\n" : '', $logs);
    }

    public function provideOutputMappingParams()
    {
        $quietMap = array(LogLevel::EMERGENCY => OutputInterface::VERBOSITY_QUIET);

        return array(
            array(LogLevel::EMERGENCY, OutputInterface::VERBOSITY_NORMAL, true),
            array(LogLevel::WARNING, OutputInterface::VERBOSITY_NORMAL, true),
            array(LogLevel::INFO, OutputInterface::VERBOSITY_NORMAL, false),
            array(LogLevel::DEBUG, OutputInterface::VERBOSITY_NORMAL, false),
            array(LogLevel::INFO, OutputInterface::VERBOSITY_VERBOSE, false),
            array(LogLevel::INFO, OutputInterface::VERBOSITY_VERY_VERBOSE, true),
            array(LogLevel::DEBUG, OutputInterface::VERBOSITY_VERY_VERBOSE, false),
            array(LogLevel::DEBUG, OutputInterface::VERBOSITY_DEBUG, true),
            array(LogLevel::ALERT, OutputInterface::VERBOSITY_QUIET, false),
            array(LogLevel::EMERGENCY, OutputInterface::VERBOSITY_QUIET, false),
            array(LogLevel::ALERT, OutputInterface::VERBOSITY_QUIET, false, $quietMap),
            array(LogLevel::EMERGENCY, OutputInterface::VERBOSITY_QUIET, true, $quietMap),
        );
    }

    public function testHasErrored()
    {
        $logger = new ConsoleLogger(new BufferedOutput());

        $this->assertFalse($logger->hasErrored());

        $logger->warning('foo');
        $this->assertFalse($logger->hasErrored());

        $logger->error('bar');
        $this->assertTrue($logger->hasErrored());
    }
}
