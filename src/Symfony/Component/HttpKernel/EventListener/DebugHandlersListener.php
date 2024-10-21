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
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Configures errors and exceptions handlers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 *
 * @internal since Symfony 5.3
 */
class DebugHandlersListener implements EventSubscriberInterface
{
    private $earlyHandler;
    private $exceptionHandler;
    private $logger;
    private $deprecationLogger;
    private $levels;
    private $throwAt;
    private $scream;
    private $scope;
    private $firstCall = true;
    private $hasTerminatedWithException;

    /**
     * @param callable|null  $exceptionHandler A handler that must support \Throwable instances that will be called on Exception
     * @param array|int|null $levels           An array map of E_* to LogLevel::* or an integer bit field of E_* constants
     * @param int|null       $throwAt          Thrown errors in a bit field of E_* constants, or null to keep the current value
     * @param bool           $scream           Enables/disables screaming mode, where even silenced errors are logged
     * @param bool           $scope            Enables/disables scoping mode
     */
    public function __construct(?callable $exceptionHandler = null, ?LoggerInterface $logger = null, $levels = \E_ALL, ?int $throwAt = \E_ALL, bool $scream = true, $scope = true, $deprecationLogger = null, $fileLinkFormat = null)
    {
        if (!\is_bool($scope)) {
            trigger_deprecation('symfony/http-kernel', '5.4', 'Passing a $fileLinkFormat is deprecated.');
            $scope = $deprecationLogger;
            $deprecationLogger = $fileLinkFormat;
        }

        $handler = set_exception_handler('is_int');
        $this->earlyHandler = \is_array($handler) ? $handler[0] : null;
        restore_exception_handler();

        $this->exceptionHandler = $exceptionHandler;
        $this->logger = $logger;
        $this->levels = $levels ?? \E_ALL;
        $this->throwAt = \is_int($throwAt) ? $throwAt : (null === $throwAt ? null : ($throwAt ? \E_ALL : null));
        $this->scream = $scream;
        $this->scope = $scope;
        $this->deprecationLogger = $deprecationLogger;
    }

    /**
     * Configures the error handler.
     */
    public function configure(?object $event = null)
    {
        if ($event instanceof ConsoleEvent && !\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            return;
        }
        if (!$event instanceof KernelEvent ? !$this->firstCall : !$event->isMainRequest()) {
            return;
        }
        $this->firstCall = $this->hasTerminatedWithException = false;
        $hasRun = null;

        $handler = set_exception_handler('is_int');
        $handler = \is_array($handler) ? $handler[0] : null;
        restore_exception_handler();

        if (!$handler instanceof ErrorHandler) {
            $handler = $this->earlyHandler;
        }

        if ($handler instanceof ErrorHandler) {
            if ($this->logger || $this->deprecationLogger) {
                $this->setDefaultLoggers($handler);
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
                    $handler->scopeAt($levels & ~\E_USER_DEPRECATED & ~\E_DEPRECATED);
                } else {
                    $handler->scopeAt(0, true);
                }
                $this->logger = $this->deprecationLogger = $this->levels = null;
            }
            if (null !== $this->throwAt) {
                $handler->throwAt($this->throwAt, true);
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
                    $app->renderThrowable($e, $output);
                };
            }
        }
        if ($this->exceptionHandler) {
            if ($handler instanceof ErrorHandler) {
                $handler->setExceptionHandler($this->exceptionHandler);
                if (null !== $hasRun) {
                    $throwAt = $handler->throwAt(0) | \E_ERROR | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR | \E_PARSE;
                    $loggers = [];

                    foreach ($handler->setLoggers([]) as $type => $log) {
                        if ($type & $throwAt) {
                            $loggers[$type] = [null, $log[1]];
                        }
                    }

                    // Assume $kernel->terminateWithException() will log uncaught exceptions appropriately
                    $handler->setLoggers($loggers);
                }
            }
            $this->exceptionHandler = null;
        }
    }

    private function setDefaultLoggers(ErrorHandler $handler): void
    {
        if (\is_array($this->levels)) {
            $levelsDeprecatedOnly = [];
            $levelsWithoutDeprecated = [];
            foreach ($this->levels as $type => $log) {
                if (\E_DEPRECATED == $type || \E_USER_DEPRECATED == $type) {
                    $levelsDeprecatedOnly[$type] = $log;
                } else {
                    $levelsWithoutDeprecated[$type] = $log;
                }
            }
        } else {
            $levelsDeprecatedOnly = $this->levels & (\E_DEPRECATED | \E_USER_DEPRECATED);
            $levelsWithoutDeprecated = $this->levels & ~\E_DEPRECATED & ~\E_USER_DEPRECATED;
        }

        $defaultLoggerLevels = $this->levels;
        if ($this->deprecationLogger && $levelsDeprecatedOnly) {
            $handler->setDefaultLogger($this->deprecationLogger, $levelsDeprecatedOnly);
            $defaultLoggerLevels = $levelsWithoutDeprecated;
        }

        if ($this->logger && $defaultLoggerLevels) {
            $handler->setDefaultLogger($this->logger, $defaultLoggerLevels);
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
