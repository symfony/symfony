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
use Symfony\Component\Console\Tests\Fixtures\DummyOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console logger test
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
            LogLevel::DEBUG => OutputInterface::VERBOSITY_NORMAL
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        return $this->output->getLogs();
    }
}
