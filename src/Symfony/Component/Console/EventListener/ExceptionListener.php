<?php

namespace Symfony\Component\Console\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArgvInput;
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

        $this->logger->error($exception->getMessage(), array('exception' => $exception));
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

        $input = new ArgvInput(null, $event->getCommand()->getDefinition());

        $this->logger->error('Command `{command}` exited with status code {code}', array('command' => (string) $input, 'code' => $exitCode));
    }

    public static function getSubscribedEvents()
    {
        return array(
            ConsoleEvents::EXCEPTION => array('onKernelException', -128),
            ConsoleEvents::TERMINATE => array('onKernelTerminate', -128),
        );
    }
}
