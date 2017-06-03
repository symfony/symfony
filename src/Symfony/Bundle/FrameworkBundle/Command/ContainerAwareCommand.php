<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class ContainerAwareCommand extends Command implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface|null
     */
    private $container;

    /**
     * @return ContainerInterface
     *
     * @throws \LogicException
     */
    protected function getContainer()
    {
        if (null === $this->container) {
            $application = $this->getApplication();
            if (null === $application) {
                throw new \LogicException('The container cannot be retrieved as the application instance is not yet set.');
            }

            $this->container = $application->getKernel()->getContainer();
        }

        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Enables printing of logged messages to the command output depending on the verbosity settings.
     *
     * @param OutputInterface $output        Command output
     * @param string          $loggerService The logger service whose messages should be echoed
     */
    protected function enableLogToOutput(OutputInterface $output, $loggerService = 'logger')
    {
        if (OutputInterface::VERBOSITY_QUIET === $output->getVerbosity()) {
            return;
        }

        $logger = $this->getContainer()->get($loggerService, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        if ($logger instanceof Logger) {
            switch ($output->getVerbosity()) {
                case OutputInterface::VERBOSITY_NORMAL:
                    $level = Logger::WARNING;
                    break;
                case OutputInterface::VERBOSITY_VERBOSE:
                    $level = Logger::NOTICE;
                    break;
                case OutputInterface::VERBOSITY_VERY_VERBOSE:
                    $level = Logger::INFO;
                    break;
                default:
                    $level = Logger::DEBUG;
                    break;
            }

            // use a custom handler that uses ConsoleOutput::getErrorOutput ('php://stderr') for errors?
            // probably also use a custom formatter that colors the output depending on log level
            $stream = $output instanceof StreamOutput ? $output->getStream() : 'php://output';
            $logger->pushHandler(new StreamHandler($stream, $level));
        }
    }
}
