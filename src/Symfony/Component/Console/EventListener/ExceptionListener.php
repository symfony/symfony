<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Console exception listener.
 *
 * Attempts to log exceptions or abnormal terminations of console commands.
 *
 * @author James Halsall <james.t.halsall@googlemail.com>
 */
class ExceptionListener implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger A logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Handles console command exception.
     *
     * @param ConsoleExceptionEvent $event Console event
     */
    public function onKernelException(ConsoleExceptionEvent $event)
    {
        if (null === $this->logger) {
            return;
        }

        $exception = $event->getException();
        $input = (string) $event->getInput();

        $this->logger->error('Exception thrown while running command: "{command}". Message: "{message}"', array('exception' => $exception, 'command' => $input, 'message' => $exception->getMessage()));
    }

    /**
     * Handles termination of console command.
     *
     * @param ConsoleTerminateEvent $event Console event
     */
    public function onKernelTerminate(ConsoleTerminateEvent $event)
    {
        if (null === $this->logger) {
            return;
        }

        $exitCode = $event->getExitCode();

        if ($exitCode === 0) {
            return;
        }

        $input = (string) $event->getInput();

        $this->logger->error('Command "{command}" exited with status code "{code}"', array('command' => (string) $input, 'code' => $exitCode));
    }

    public static function getSubscribedEvents()
    {
        return array(
            ConsoleEvents::EXCEPTION => array('onKernelException', -128),
            ConsoleEvents::TERMINATE => array('onKernelTerminate', -128),
        );
    }
}
