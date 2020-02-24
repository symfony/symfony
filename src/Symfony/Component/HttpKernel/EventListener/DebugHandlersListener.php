<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Configures errors and exceptions handlers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 */
class DebugHandlersListener implements EventSubscriberInterface
{
    private $exceptionHandler;
    private $logger;
    private $levels;
    private $throwAt;
    private $scream;
    private $fileLinkFormat;
    private $scope;
    private $firstCall = true;
    private $hasTerminatedWithException;

    /**
     * @param callable|null                 $exceptionHandler A handler that must support \Throwable instances that will be called on Exception
     * @param array|int                     $levels           An array map of E_* to LogLevel::* or an integer bit field of E_* constants
     * @param int|null                      $throwAt          Thrown errors in a bit field of E_* constants, or null to keep the current value
     * @param bool                          $scream           Enables/disables screaming mode, where even silenced errors are logged
     * @param string|FileLinkFormatter|null $fileLinkFormat   The format for links to source files
     * @param bool                          $scope            Enables/disables scoping mode
     */
    public function __construct(callable $exceptionHandler = null, LoggerInterface $logger = null, $levels = E_ALL, ?int $throwAt = E_ALL, bool $scream = true, $fileLinkFormat = null, bool $scope = true)
    {
        $this->exceptionHandler = $exceptionHandler;
        $this->logger = $logger;
        $this->levels = null === $levels ? E_ALL : $levels;
        $this->throwAt = \is_int($throwAt) ? $throwAt : (null === $throwAt ? null : ($throwAt ? E_ALL : null));
        $this->scream = $scream;
        $this->fileLinkFormat = $fileLinkFormat;
        $this->scope = $scope;
    }

    /**
     * Configures the error handler.
     */
    public function configure(object $event = null)
    {
        if ($event instanceof ConsoleEvent && !\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            return;
        }
        if (!$event instanceof KernelEvent ? !$this->firstCall : !$event->isMasterRequest()) {
            return;
        }
        $this->firstCall = $this->hasTerminatedWithException = false;

        $handler = set_exception_handler('var_dump');
        $handler = \is_array($handler) ? $handler[0] : null;
        restore_exception_handler();

        if ($this->logger || null !== $this->throwAt) {
            if ($handler instanceof ErrorHandler) {
                if ($this->logger) {
                    $handler->setDefaultLogger($this->logger, $this->levels);
                    if (\is_array($this->levels)) {
                        $levels = 0;
                        foreach ($this->levels as $type => $log) {
                            $levels |= $type;
                        }
                    } else {
                        $levels = $this->levels;
                    }
                    if ($this->scream) {
                        $handler->screamAt($levels);
                    }
                    if ($this->scope) {
                        $handler->scopeAt($levels & ~E_USER_DEPRECATED & ~E_DEPRECATED);
                    } else {
                        $handler->scopeAt(0, true);
                    }
                    $this->logger = $this->levels = null;
                }
                if (null !== $this->throwAt) {
                    $handler->throwAt($this->throwAt, true);
                }
            }
        }
        if (!$this->exceptionHandler) {
            if ($event instanceof KernelEvent) {
                if (method_exists($kernel = $event->getKernel(), 'terminateWithException')) {
                    $request = $event->getRequest();
                    $hasRun = &$this->hasTerminatedWithException;
                    $this->exceptionHandler = static function (\Throwable $e) use ($kernel, $request, &$hasRun) {
                        if ($hasRun) {
                            throw $e;
                        }

                        $hasRun = true;
                        $kernel->terminateWithException($e, $request);
                    };
                }
            } elseif ($event instanceof ConsoleEvent && $app = $event->getCommand()->getApplication()) {
                $output = $event->getOutput();
                if ($output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }
                $this->exceptionHandler = static function (\Throwable $e) use ($app, $output) {
                    if (method_exists($app, 'renderThrowable')) {
                        $app->renderThrowable($e, $output);
                    } else {
                        if (!$e instanceof \Exception) {
                            $e = new FatalThrowableError($e);
                        }

                        $app->renderException($e, $output);
                    }
                };
            }
        }
        if ($this->exceptionHandler) {
            if ($handler instanceof ErrorHandler) {
                $handler->setExceptionHandler($this->exceptionHandler);
            }
            $this->exceptionHandler = null;
        }
    }

    public static function getSubscribedEvents(): array
    {
        $events = [KernelEvents::REQUEST => ['configure', 2048]];

        if (\defined('Symfony\Component\Console\ConsoleEvents::COMMAND')) {
            $events[ConsoleEvents::COMMAND] = ['configure', 2048];
        }

        return $events;
    }
}
