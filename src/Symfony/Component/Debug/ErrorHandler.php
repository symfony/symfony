<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\Debug\Exception\OutOfMemoryException;
use Symfony\Component\Debug\FatalErrorHandler\UndefinedFunctionFatalErrorHandler;
use Symfony\Component\Debug\FatalErrorHandler\UndefinedMethodFatalErrorHandler;
use Symfony\Component\Debug\FatalErrorHandler\ClassNotFoundFatalErrorHandler;
use Symfony\Component\Debug\FatalErrorHandler\FatalErrorHandlerInterface;

/**
 * A generic ErrorHandler for the PHP engine.
 *
 * Provides five bit fields that control how errors are handled:
 * - thrownErrors: errors thrown as \ErrorException
 * - loggedErrors: logged errors, when not @-silenced
 * - scopedErrors: errors thrown or logged with their local context
 * - tracedErrors: errors logged with their stack trace, only once for repeated errors
 * - screamedErrors: never @-silenced errors
 *
 * Each error level can be logged by a dedicated PSR-3 logger object.
 * Screaming only applies to logging.
 * Throwing takes precedence over logging.
 * Uncaught exceptions are logged as E_ERROR.
 * E_DEPRECATED and E_USER_DEPRECATED levels never throw.
 * E_RECOVERABLE_ERROR and E_USER_ERROR levels always throw.
 * Non catchable errors that can be detected at shutdown time are logged when the scream bit field allows so.
 * As errors have a performance cost, repeated errors are all logged, so that the developer
 * can see them and weight them as more important to fix than others of the same level.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ErrorHandler
{
    private $levels = array(
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated',
        E_NOTICE => 'Notice',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
        E_WARNING => 'Warning',
        E_USER_WARNING => 'User Warning',
        E_COMPILE_WARNING => 'Compile Warning',
        E_CORE_WARNING => 'Core Warning',
        E_USER_ERROR => 'User Error',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_COMPILE_ERROR => 'Compile Error',
        E_PARSE => 'Parse Error',
        E_ERROR => 'Error',
        E_CORE_ERROR => 'Core Error',
    );

    private $loggers = array(
        E_DEPRECATED => array(null, LogLevel::INFO),
        E_USER_DEPRECATED => array(null, LogLevel::INFO),
        E_NOTICE => array(null, LogLevel::WARNING),
        E_USER_NOTICE => array(null, LogLevel::WARNING),
        E_STRICT => array(null, LogLevel::WARNING),
        E_WARNING => array(null, LogLevel::WARNING),
        E_USER_WARNING => array(null, LogLevel::WARNING),
        E_COMPILE_WARNING => array(null, LogLevel::WARNING),
        E_CORE_WARNING => array(null, LogLevel::WARNING),
        E_USER_ERROR => array(null, LogLevel::CRITICAL),
        E_RECOVERABLE_ERROR => array(null, LogLevel::CRITICAL),
        E_COMPILE_ERROR => array(null, LogLevel::CRITICAL),
        E_PARSE => array(null, LogLevel::CRITICAL),
        E_ERROR => array(null, LogLevel::CRITICAL),
        E_CORE_ERROR => array(null, LogLevel::CRITICAL),
    );

    private $thrownErrors = 0x1FFF; // E_ALL - E_DEPRECATED - E_USER_DEPRECATED
    private $scopedErrors = 0x1FFF; // E_ALL - E_DEPRECATED - E_USER_DEPRECATED
    private $tracedErrors = 0x77FB; // E_ALL - E_STRICT - E_PARSE
    private $screamedErrors = 0x55; // E_ERROR + E_CORE_ERROR + E_COMPILE_ERROR + E_PARSE
    private $loggedErrors = 0;

    private $loggedTraces = array();
    private $isRecursive = 0;
    private $isRoot = false;
    private $exceptionHandler;
    private $bootstrappingLogger;

    private static $reservedMemory;
    private static $stackedErrors = array();
    private static $stackedErrorLevels = array();
    private static $toStringException = null;

    /**
     * Registers the error handler.
     *
     * @param self|null $handler The handler to register
     * @param bool      $replace Whether to replace or not any existing handler
     *
     * @return self The registered error handler
     */
    public static function register(self $handler = null, $replace = true)
    {
        if (null === self::$reservedMemory) {
            self::$reservedMemory = str_repeat('x', 10240);
            register_shutdown_function(__CLASS__.'::handleFatalError');
        }

        if ($handlerIsNew = null === $handler) {
            $handler = new static();
        }

        if (null === $prev = set_error_handler(array($handler, 'handleError'))) {
            restore_error_handler();
            // Specifying the error types earlier would expose us to https://bugs.php.net/63206
            set_error_handler(array($handler, 'handleError'), $handler->thrownErrors | $handler->loggedErrors);
            $handler->isRoot = true;
        }

        if ($handlerIsNew && is_array($prev) && $prev[0] instanceof self) {
            $handler = $prev[0];
            $replace = false;
        }
        if ($replace || !$prev) {
            $handler->setExceptionHandler(set_exception_handler(array($handler, 'handleException')));
        } else {
            restore_error_handler();
        }

        $handler->throwAt(E_ALL & $handler->thrownErrors, true);

        return $handler;
    }

    public function __construct(BufferingLogger $bootstrappingLogger = null)
    {
        if ($bootstrappingLogger) {
            $this->bootstrappingLogger = $bootstrappingLogger;
            $this->setDefaultLogger($bootstrappingLogger);
        }
    }

    /**
     * Sets a logger to non assigned errors levels.
     *
     * @param LoggerInterface $logger  A PSR-3 logger to put as default for the given levels
     * @param array|int       $levels  An array map of E_* to LogLevel::* or an integer bit field of E_* constants
     * @param bool            $replace Whether to replace or not any existing logger
     */
    public function setDefaultLogger(LoggerInterface $logger, $levels = E_ALL, $replace = false)
    {
        $loggers = array();

        if (is_array($levels)) {
            foreach ($levels as $type => $logLevel) {
                if (empty($this->loggers[$type][0]) || $replace || $this->loggers[$type][0] === $this->bootstrappingLogger) {
                    $loggers[$type] = array($logger, $logLevel);
                }
            }
        } else {
            if (null === $levels) {
                $levels = E_ALL;
            }
            foreach ($this->loggers as $type => $log) {
                if (($type & $levels) && (empty($log[0]) || $replace || $log[0] === $this->bootstrappingLogger)) {
                    $log[0] = $logger;
                    $loggers[$type] = $log;
                }
            }
        }

        $this->setLoggers($loggers);
    }

    /**
     * Sets a logger for each error level.
     *
     * @param array $loggers Error levels to [LoggerInterface|null, LogLevel::*] map
     *
     * @return array The previous map
     *
     * @throws \InvalidArgumentException
     */
    public function setLoggers(array $loggers)
    {
        $prevLogged = $this->loggedErrors;
        $prev = $this->loggers;
        $flush = array();

        foreach ($loggers as $type => $log) {
            if (!isset($prev[$type])) {
                throw new \InvalidArgumentException('Unknown error type: '.$type);
            }
            if (!is_array($log)) {
                $log = array($log);
            } elseif (!array_key_exists(0, $log)) {
                throw new \InvalidArgumentException('No logger provided');
            }
            if (null === $log[0]) {
                $this->loggedErrors &= ~$type;
            } elseif ($log[0] instanceof LoggerInterface) {
                $this->loggedErrors |= $type;
            } else {
                throw new \InvalidArgumentException('Invalid logger provided');
            }
            $this->loggers[$type] = $log + $prev[$type];

            if ($this->bootstrappingLogger && $prev[$type][0] === $this->bootstrappingLogger) {
                $flush[$type] = $type;
            }
        }
        $this->reRegister($prevLogged | $this->thrownErrors);

        if ($flush) {
            foreach ($this->bootstrappingLogger->cleanLogs() as $log) {
                $type = $log[2]['type'];
                if (!isset($flush[$type])) {
                    $this->bootstrappingLogger->log($log[0], $log[1], $log[2]);
                } elseif ($this->loggers[$type][0]) {
                    $this->loggers[$type][0]->log($this->loggers[$type][1], $log[1], $log[2]);
                }
            }
        }

        return $prev;
    }

    /**
     * Sets a user exception handler.
     *
     * @param callable $handler A handler that will be called on Exception
     *
     * @return callable|null The previous exception handler
     */
    public function setExceptionHandler(callable $handler = null)
    {
        $prev = $this->exceptionHandler;
        $this->exceptionHandler = $handler;

        return $prev;
    }

    /**
     * Sets the PHP error levels that throw an exception when a PHP error occurs.
     *
     * @param int  $levels  A bit field of E_* constants for thrown errors
     * @param bool $replace Replace or amend the previous value
     *
     * @return int The previous value
     */
    public function throwAt($levels, $replace = false)
    {
        $prev = $this->thrownErrors;
        $this->thrownErrors = ($levels | E_RECOVERABLE_ERROR | E_USER_ERROR) & ~E_USER_DEPRECATED & ~E_DEPRECATED;
        if (!$replace) {
            $this->thrownErrors |= $prev;
        }
        $this->reRegister($prev | $this->loggedErrors);

        return $prev;
    }

    /**
     * Sets the PHP error levels for which local variables are preserved.
     *
     * @param int  $levels  A bit field of E_* constants for scoped errors
     * @param bool $replace Replace or amend the previous value
     *
     * @return int The previous value
     */
    public function scopeAt($levels, $replace = false)
    {
        $prev = $this->scopedErrors;
        $this->scopedErrors = (int) $levels;
        if (!$replace) {
            $this->scopedErrors |= $prev;
        }

        return $prev;
    }

    /**
     * Sets the PHP error levels for which the stack trace is preserved.
     *
     * @param int  $levels  A bit field of E_* constants for traced errors
     * @param bool $replace Replace or amend the previous value
     *
     * @return int The previous value
     */
    public function traceAt($levels, $replace = false)
    {
        $prev = $this->tracedErrors;
        $this->tracedErrors = (int) $levels;
        if (!$replace) {
            $this->tracedErrors |= $prev;
        }

        return $prev;
    }

    /**
     * Sets the error levels where the @-operator is ignored.
     *
     * @param int  $levels  A bit field of E_* constants for screamed errors
     * @param bool $replace Replace or amend the previous value
     *
     * @return int The previous value
     */
    public function screamAt($levels, $replace = false)
    {
        $prev = $this->screamedErrors;
        $this->screamedErrors = (int) $levels;
        if (!$replace) {
            $this->screamedErrors |= $prev;
        }

        return $prev;
    }

    /**
     * Re-registers as a PHP error handler if levels changed.
     */
    private function reRegister($prev)
    {
        if ($prev !== $this->thrownErrors | $this->loggedErrors) {
            $handler = set_error_handler('var_dump');
            $handler = is_array($handler) ? $handler[0] : null;
            restore_error_handler();
            if ($handler === $this) {
                restore_error_handler();
                if ($this->isRoot) {
                    set_error_handler(array($this, 'handleError'), $this->thrownErrors | $this->loggedErrors);
                } else {
                    set_error_handler(array($this, 'handleError'));
                }
            }
        }
    }

    /**
     * Handles errors by filtering then logging them according to the configured bit fields.
     *
     * @param int    $type    One of the E_* constants
     * @param string $file
     * @param int    $line
     * @param array  $context
     *
     * @return bool Returns false when no handling happens so that the PHP engine can handle the error itself
     *
     * @throws \ErrorException When $this->thrownErrors requests so
     *
     * @internal
     */
    public function handleError($type, $message, $file, $line, array $context, array $backtrace = null)
    {
        $level = error_reporting() | E_RECOVERABLE_ERROR | E_USER_ERROR | E_DEPRECATED | E_USER_DEPRECATED;
        $log = $this->loggedErrors & $type;
        $throw = $this->thrownErrors & $type & $level;
        $type &= $level | $this->screamedErrors;

        if (!$type || (!$log && !$throw)) {
            return $type && $log;
        }

        if (null !== $backtrace && $type & E_ERROR) {
            // E_ERROR fatal errors are triggered on HHVM when
            // hhvm.error_handling.call_user_handler_on_fatals=1
            // which is the way to get their backtrace.
            $this->handleFatalError(compact('type', 'message', 'file', 'line', 'backtrace'));

            return true;
        }

        if ($throw) {
            if (null !== self::$toStringException) {
                $throw = self::$toStringException;
                self::$toStringException = null;
            } elseif (($this->scopedErrors & $type) && class_exists(ContextErrorException::class)) {
                $throw = new ContextErrorException($this->levels[$type].': '.$message, 0, $type, $file, $line, $context);
            } else {
                $throw = new \ErrorException($this->levels[$type].': '.$message, 0, $type, $file, $line);
            }

            if (E_USER_ERROR & $type) {
                $backtrace = $backtrace ?: $throw->getTrace();

                for ($i = 1; isset($backtrace[$i]); ++$i) {
                    if (isset($backtrace[$i]['function'], $backtrace[$i]['type'], $backtrace[$i - 1]['function'])
                        && '__toString' === $backtrace[$i]['function']
                        && '->' === $backtrace[$i]['type']
                        && !isset($backtrace[$i - 1]['class'])
                        && ('trigger_error' === $backtrace[$i - 1]['function'] || 'user_error' === $backtrace[$i - 1]['function'])
                    ) {
                        // Here, we know trigger_error() has been called from __toString().
                        // HHVM is fine with throwing from __toString() but PHP triggers a fatal error instead.
                        // A small convention allows working around the limitation:
                        // given a caught $e exception in __toString(), quitting the method with
                        // `return trigger_error($e, E_USER_ERROR);` allows this error handler
                        // to make $e get through the __toString() barrier.

                        foreach ($context as $e) {
                            if (($e instanceof \Exception || $e instanceof \Throwable) && $e->__toString() === $message) {
                                if (1 === $i) {
                                    // On HHVM
                                    $throw = $e;
                                    break;
                                }
                                self::$toStringException = $e;

                                return true;
                            }
                        }

                        if (1 < $i) {
                            // On PHP (not on HHVM), display the original error message instead of the default one.
                            $this->handleException($throw);

                            // Stop the process by giving back the error to the native handler.
                            return false;
                        }
                    }
                }
            }

            throw $throw;
        }

        // For duplicated errors, log the trace only once
        $e = md5("{$type}/{$line}/{$file}\x00{$message}", true);
        $trace = true;

        if (!($this->tracedErrors & $type) || isset($this->loggedTraces[$e])) {
            $trace = false;
        } else {
            $this->loggedTraces[$e] = 1;
        }

        $e = compact('type', 'file', 'line', 'level');

        if ($type & $level) {
            if ($this->scopedErrors & $type) {
                $e['scope_vars'] = $context;
                if ($trace) {
                    $e['stack'] = $backtrace ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
                }
            } elseif ($trace) {
                if (null === $backtrace) {
                    $e['stack'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                } else {
                    foreach ($backtrace as &$frame) {
                        unset($frame['args'], $frame);
                    }
                    $e['stack'] = $backtrace;
                }
            }
        }

        if ($this->isRecursive) {
            $log = 0;
        } elseif (self::$stackedErrorLevels) {
            self::$stackedErrors[] = array($this->loggers[$type][0], ($type & $level) ? $this->loggers[$type][1] : LogLevel::DEBUG, $message, $e);
        } else {
            try {
                $this->isRecursive = true;
                $this->loggers[$type][0]->log(($type & $level) ? $this->loggers[$type][1] : LogLevel::DEBUG, $message, $e);
            } finally {
                $this->isRecursive = false;
            }
        }

        return $type && $log;
    }

    /**
     * Handles an exception by logging then forwarding it to another handler.
     *
     * @param \Exception|\Throwable $exception An exception to handle
     * @param array                 $error     An array as returned by error_get_last()
     *
     * @internal
     */
    public function handleException($exception, array $error = null)
    {
        if (!$exception instanceof \Exception) {
            $exception = new FatalThrowableError($exception);
        }
        $type = $exception instanceof FatalErrorException ? $exception->getSeverity() : E_ERROR;

        if (($this->loggedErrors & $type) || $exception instanceof FatalThrowableError) {
            $e = array(
                'type' => $type,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'level' => error_reporting(),
                'stack' => $exception->getTrace(),
            );
            if ($exception instanceof FatalErrorException) {
                if ($exception instanceof FatalThrowableError) {
                    $error = array(
                        'type' => $type,
                        'message' => $message = $exception->getMessage(),
                        'file' => $e['file'],
                        'line' => $e['line'],
                    );
                } else {
                    $message = 'Fatal '.$exception->getMessage();
                }
            } elseif ($exception instanceof \ErrorException) {
                $message = 'Uncaught '.$exception->getMessage();
                if ($exception instanceof ContextErrorException) {
                    $e['context'] = $exception->getContext();
                }
            } else {
                $message = 'Uncaught Exception: '.$exception->getMessage();
            }
        }
        if ($this->loggedErrors & $type) {
            $this->loggers[$type][0]->log($this->loggers[$type][1], $message, $e);
        }
        if ($exception instanceof FatalErrorException && !$exception instanceof OutOfMemoryException && $error) {
            foreach ($this->getFatalErrorHandlers() as $handler) {
                if ($e = $handler->handleError($error, $exception)) {
                    $exception = $e;
                    break;
                }
            }
        }
        if (empty($this->exceptionHandler)) {
            throw $exception; // Give back $exception to the native handler
        }
        try {
            call_user_func($this->exceptionHandler, $exception);
        } catch (\Exception $handlerException) {
        } catch (\Throwable $handlerException) {
        }
        if (isset($handlerException)) {
            $this->exceptionHandler = null;
            $this->handleException($handlerException);
        }
    }

    /**
     * Shutdown registered function for handling PHP fatal errors.
     *
     * @param array $error An array as returned by error_get_last()
     *
     * @internal
     */
    public static function handleFatalError(array $error = null)
    {
        if (null === self::$reservedMemory) {
            return;
        }

        self::$reservedMemory = null;

        $handler = set_error_handler('var_dump');
        $handler = is_array($handler) ? $handler[0] : null;
        restore_error_handler();

        if (!$handler instanceof self) {
            return;
        }

        if (null === $error) {
            $error = error_get_last();
        }

        try {
            while (self::$stackedErrorLevels) {
                static::unstackErrors();
            }
        } catch (\Exception $exception) {
            // Handled below
        } catch (\Throwable $exception) {
            // Handled below
        }

        if ($error && $error['type'] &= E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR) {
            // Let's not throw anymore but keep logging
            $handler->throwAt(0, true);
            $trace = isset($error['backtrace']) ? $error['backtrace'] : null;

            if (0 === strpos($error['message'], 'Allowed memory') || 0 === strpos($error['message'], 'Out of memory')) {
                $exception = new OutOfMemoryException($handler->levels[$error['type']].': '.$error['message'], 0, $error['type'], $error['file'], $error['line'], 2, false, $trace);
            } else {
                $exception = new FatalErrorException($handler->levels[$error['type']].': '.$error['message'], 0, $error['type'], $error['file'], $error['line'], 2, true, $trace);
            }
        } elseif (!isset($exception)) {
            return;
        }

        try {
            $handler->handleException($exception, $error);
        } catch (FatalErrorException $e) {
            // Ignore this re-throw
        }
    }

    /**
     * Configures the error handler for delayed handling.
     * Ensures also that non-catchable fatal errors are never silenced.
     *
     * As shown by http://bugs.php.net/42098 and http://bugs.php.net/60724
     * PHP has a compile stage where it behaves unusually. To workaround it,
     * we plug an error handler that only stacks errors for later.
     *
     * The most important feature of this is to prevent
     * autoloading until unstackErrors() is called.
     */
    public static function stackErrors()
    {
        self::$stackedErrorLevels[] = error_reporting(error_reporting() | E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);
    }

    /**
     * Unstacks stacked errors and forwards to the logger.
     */
    public static function unstackErrors()
    {
        $level = array_pop(self::$stackedErrorLevels);

        if (null !== $level) {
            $e = error_reporting($level);
            if ($e !== ($level | E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR)) {
                // If the user changed the error level, do not overwrite it
                error_reporting($e);
            }
        }

        if (empty(self::$stackedErrorLevels)) {
            $errors = self::$stackedErrors;
            self::$stackedErrors = array();

            foreach ($errors as $e) {
                $e[0]->log($e[1], $e[2], $e[3]);
            }
        }
    }

    /**
     * Gets the fatal error handlers.
     *
     * Override this method if you want to define more fatal error handlers.
     *
     * @return FatalErrorHandlerInterface[] An array of FatalErrorHandlerInterface
     */
    protected function getFatalErrorHandlers()
    {
        return array(
            new UndefinedFunctionFatalErrorHandler(),
            new UndefinedMethodFatalErrorHandler(),
            new ClassNotFoundFatalErrorHandler(),
        );
    }
}
