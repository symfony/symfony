<?php

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
 * @author  James Halsall <james.t.halsall@googlemail.com>
 */
class ExceptionListener implements EventSubscriberInterface
{
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

        $message = sprintf(
            '%s: %s (uncaught exception) at %s line %s while running console command `%s`',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $event->getCommand()->getName()
        );

        $this->logger->error($message, array('exception' => $exception));
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

        if ($exitCode > 255) {
            $event->setExitCode(255);
        }

        $message = sprintf('Command `%s` exited with status code %d');

        $this->logger->warning($message);
    }

    public static function getSubscribedEvents()
    {
        return array(
            ConsoleEvents::EXCEPTION => array('onKernelException', -128),
            ConsoleEvents::TERMINATE => array('onKernelTerminate', -128),
        );
    }
}
