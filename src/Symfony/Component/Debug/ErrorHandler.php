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

use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Psr\Log\LoggerInterface;

/**
 * ErrorHandler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Konstantin Myakshin <koc-dp@yandex.ru>
 */
class ErrorHandler
{
    const TYPE_DEPRECATION = -100;

    private $levels = array(
        E_WARNING           => 'Warning',
        E_NOTICE            => 'Notice',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated',
        E_ERROR             => 'Error',
        E_CORE_ERROR        => 'Core Error',
        E_COMPILE_ERROR     => 'Compile Error',
        E_PARSE             => 'Parse',
    );

    private $level;

    private $reservedMemory;

    private $displayErrors;

    /**
     * @var LoggerInterface[] Loggers for channels
     */
    private static $loggers = array();

    /**
     * Registers the error handler.
     *
     * @param integer $level The level at which the conversion to Exception is done (null to use the error_reporting() value and 0 to disable)
     * @param Boolean $displayErrors Display errors (for dev environment) or just log they (production usage)
     *
     * @return The registered error handler
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

    public function setLevel($level)
    {
        $this->level = null === $level ? error_reporting() : $level;
    }

    public function setDisplayErrors($displayErrors)
    {
        $this->displayErrors = $displayErrors;
    }

    public static function setLogger(LoggerInterface $logger, $channel = 'deprecation')
    {
        self::$loggers[$channel] = $logger;
    }

    /**
     * @throws ContextErrorException When error_reporting returns error
     */
    public function handle($level, $message, $file = 'unknown', $line = 0, $context = array())
    {
        if (0 === $this->level) {
            return false;
        }

        if ($level & (E_USER_DEPRECATED | E_DEPRECATED)) {
            if (isset(self::$loggers['deprecation'])) {
                if (version_compare(PHP_VERSION, '5.4', '<')) {
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

        if ($this->displayErrors && error_reporting() & $level && $this->level & $level) {
            throw new ContextErrorException(sprintf('%s: %s in %s line %d', isset($this->levels[$level]) ? $this->levels[$level] : $level, $message, $file, $line), 0, $level, $file, $line, $context);
        }

        return false;
    }

    public function handleFatal()
    {
        if (null === $error = error_get_last()) {
            return;
        }

        unset($this->reservedMemory);
        $type = $error['type'];
        if (0 === $this->level || !in_array($type, array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE))) {
            return;
        }

        if (isset(self::$loggers['emergency'])) {
            $fatal = array(
                'type' => $type,
                'file' => $error['file'],
                'line' => $error['line'],
            );

            self::$loggers['emergency']->emerg($error['message'], $fatal);
        }

        if (!$this->displayErrors) {
            return;
        }

        // get current exception handler
        $exceptionHandler = set_exception_handler(function() {});
        restore_exception_handler();

        if (is_array($exceptionHandler) && $exceptionHandler[0] instanceof ExceptionHandler) {
            $level = isset($this->levels[$type]) ? $this->levels[$type] : $type;
            $message = sprintf('%s: %s in %s line %d', $level, $error['message'], $error['file'], $error['line']);
            $exception = new FatalErrorException($message, 0, $type, $error['file'], $error['line']);
            $exceptionHandler[0]->handle($exception);
        }
    }
}
