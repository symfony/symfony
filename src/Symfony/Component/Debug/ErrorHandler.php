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
use Symfony\Component\Debug\Exception\OutOfMemoryException;
use Symfony\Component\Debug\FatalErrorHandler\UndefinedFunctionFatalErrorHandler;
use Symfony\Component\Debug\FatalErrorHandler\UndefinedMethodFatalErrorHandler;
use Symfony\Component\Debug\FatalErrorHandler\ClassNotFoundFatalErrorHandler;
use Symfony\Component\Debug\FatalErrorHandler\FatalErrorHandlerInterface;

/**
 * ErrorHandler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Konstantin Myakshin <koc-dp@yandex.ru>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ErrorHandler
{
    const TYPE_DEPRECATION = -100;

    private $levels = array(
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated',
        E_ERROR => 'Error',
        E_CORE_ERROR => 'Core Error',
        E_COMPILE_ERROR => 'Compile Error',
        E_PARSE => 'Parse Error',
    );

    private $level;

    private $reservedMemory;

    private $displayErrors;

    /**
     * @var LoggerInterface[] Loggers for channels
     */
    private static $loggers = array();

    private static $stackedErrors = array();

    private static $stackedErrorLevels = array();

    /**
     * Registers the error handler.
     *
     * @param int  $level         The level at which the conversion to Exception is done (null to use the error_reporting() value and 0 to disable)
     * @param bool $displayErrors Display errors (for dev environment) or just log them (production usage)
     *
     * @return ErrorHandler The registered error handler
     */
    public static function register($level = null, $displayErrors = true)
    {
        $handler = new static();
        $handler->setLevel($level);
        $handler->setDisplayErrors($displayErrors);

        ini_set('display_errors', 0);
        set_error_handler(array($handler, 'handle'));
        register_shutdown_function(array($handler, 'handleFatal'));
        $handler->reservedMemory = str_repeat('x', 10240);

        return $handler;
    }

    /**
     * Sets the level at which the conversion to Exception is done.
     *
     * @param int|null     $level The level (null to use the error_reporting() value and 0 to disable)
     */
    public function setLevel($level)
    {
        $this->level = null === $level ? error_reporting() : $level;
    }

    /**
     * Sets the display_errors flag value.
     *
     * @param int     $displayErrors The display_errors flag value
     */
    public function setDisplayErrors($displayErrors)
    {
        $this->displayErrors = $displayErrors;
    }

    /**
     * Sets a logger for the given channel.
     *
     * @param LoggerInterface $logger  A logger interface
     * @param string          $channel The channel associated with the logger (deprecation, emergency or scream)
     */
    public static function setLogger(LoggerInterface $logger, $channel = 'deprecation')
    {
        self::$loggers[$channel] = $logger;
    }

    /**
     * @throws \ErrorException When error_reporting returns error
     */
    public function handle($level, $message, $file = 'unknown', $line = 0, $context = array())
    {
        if ($level & (E_USER_DEPRECATED | E_DEPRECATED)) {
            if (isset(self::$loggers['deprecation'])) {
                if (self::$stackedErrorLevels) {
                    self::$stackedErrors[] = func_get_args();
                } else {
                    if (PHP_VERSION_ID < 50400) {
                        $stack = array_map(
                            function ($row) {
                                unset($row['args']);

                                return $row;
                            },
                            array_slice(debug_backtrace(false), 0, 10)
                        );
                    } else {
                        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
                    }

                    self::$loggers['deprecation']->warning($message, array('type' => self::TYPE_DEPRECATION, 'stack' => $stack));
                }

                return true;
            }
        } elseif ($this->displayErrors && error_reporting() & $level && $this->level & $level) {
            if (PHP_VERSION_ID < 50400 && isset($context['GLOBALS']) && is_array($context)) {
                $c = $context;                  // Whatever the signature of the method,
                unset($c['GLOBALS'], $context); // $context is always a reference in 5.3
                $context = $c;
            }

            $exception = sprintf('%s: %s in %s line %d', isset($this->levels[$level]) ? $this->levels[$level] : $level, $message, $file, $line);
            if ($context && class_exists('Symfony\Component\Debug\Exception\ContextErrorException')) {
                // Checking for class existence is a work around for https://bugs.php.net/42098
                $exception = new ContextErrorException($exception, 0, $level, $file, $line, $context);
            } else {
                $exception = new \ErrorException($exception, 0, $level, $file, $line);
            }

            if (PHP_VERSION_ID <= 50407 && (PHP_VERSION_ID >= 50400 || PHP_VERSION_ID <= 50317)) {
                // Exceptions thrown from error handlers are sometimes not caught by the exception
                // handler and shutdown handlers are bypassed before 5.4.8/5.3.18.
                // We temporarily re-enable display_errors to prevent any blank page related to this bug.

                $exception->errorHandlerCanary = new ErrorHandlerCanary();
            }

            throw $exception;
        }

        if (isset(self::$loggers['scream']) && !(error_reporting() & $level)) {
            if (self::$stackedErrorLevels) {
                self::$stackedErrors[] = func_get_args();
            } else {
                switch ($level) {
                    case E_USER_ERROR:
                    case E_RECOVERABLE_ERROR:
                        $logLevel = LogLevel::ERROR;
                        break;

                    case E_WARNING:
                    case E_USER_WARNING:
                        $logLevel = LogLevel::WARNING;
                        break;

                    default:
                        $logLevel = LogLevel::NOTICE;
                        break;
                }

                self::$loggers['scream']->log($logLevel, $message, array(
                    'type' => $level,
                    'file' => $file,
                    'line' => $line,
                    'scream' => error_reporting(),
                ));
            }
        }

        return false;
    }

    /**
     * Configure the error handler for delayed handling.
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
     * Unstacks stacked errors and forwards to the regular handler
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

            $errorHandler = set_error_handler('var_dump');
            restore_error_handler();

            if ($errorHandler) {
                foreach ($errors as $e) {
                    call_user_func_array($errorHandler, $e);
                }
            }
        }
    }

    public function handleFatal()
    {
        $this->reservedMemory = '';
        gc_collect_cycles();
        $error = error_get_last();

        // get current exception handler
        $exceptionHandler = set_exception_handler('var_dump');
        restore_exception_handler();

        try {
            while (self::$stackedErrorLevels) {
                static::unstackErrors();
            }
        } catch (\Exception $exception) {
            if ($exceptionHandler) {
                call_user_func($exceptionHandler, $exception);

                return;
            }

            if ($this->displayErrors) {
                ini_set('display_errors', 1);
            }

            throw $exception;
        }

        if (!$error || !$this->level || !($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
            return;
        }

        if (isset(self::$loggers['emergency'])) {
            $fatal = array(
                'type' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line'],
            );

            self::$loggers['emergency']->emergency($error['message'], $fatal);
        }

        if ($this->displayErrors && $exceptionHandler) {
            $this->handleFatalError($exceptionHandler, $error);
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

    private function handleFatalError($exceptionHandler, array $error)
    {
        // Let PHP handle any further error
        set_error_handler('var_dump', 0);
        ini_set('display_errors', 1);

        $level = isset($this->levels[$error['type']]) ? $this->levels[$error['type']] : $error['type'];
        $message = sprintf('%s: %s in %s line %d', $level, $error['message'], $error['file'], $error['line']);
        if (0 === strpos($error['message'], 'Allowed memory') || 0 === strpos($error['message'], 'Out of memory')) {
            $exception = new OutOfMemoryException($message, 0, $error['type'], $error['file'], $error['line'], 3, false);
        } else {
            $exception = new FatalErrorException($message, 0, $error['type'], $error['file'], $error['line'], 3, true);

            foreach ($this->getFatalErrorHandlers() as $handler) {
                if ($e = $handler->handleError($error, $exception)) {
                    $exception = $e;
                    break;
                }
            }
        }

        try {
            call_user_func($exceptionHandler, $exception);
        } catch (\Exception $e) {
            // The handler failed. Let PHP handle that now.
            throw $exception;
        }
    }
}

/**
 * Private class used to work around https://bugs.php.net/54275
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class ErrorHandlerCanary
{
    private static $displayErrors = null;

    public function __construct()
    {
        if (null === self::$displayErrors) {
            self::$displayErrors = ini_set('display_errors', 1);
        }
    }

    public function __destruct()
    {
        if (null !== self::$displayErrors) {
            ini_set('display_errors', self::$displayErrors);
            self::$displayErrors = null;
        }
    }
}
