<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Debug;

use Symfony\Component\HttpKernel\Exception\FatalErrorException;

/**
 * ErrorHandler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ErrorHandler
{
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

    /**
     * Register the error handler.
     *
     * @param integer $level The level at which the conversion to Exception is done (null to use the error_reporting() value and 0 to disable)
     *
     * @return The registered error handler
     */
    public static function register($level = null)
    {
        $handler = new static();
        $handler->setLevel($level);

        set_error_handler(array($handler, 'handle'));
        register_shutdown_function(array($handler, 'handleFatall'));

        return $handler;
    }

    public function setLevel($level)
    {
        $this->level = null === $level ? error_reporting() : $level;
    }

    /**
     * @throws \ErrorException When error_reporting returns error
     */
    public function handle($level, $message, $file, $line, $context)
    {
        if (0 === $this->level) {
            return false;
        }

        if (error_reporting() & $level && $this->level & $level) {
            throw new \ErrorException(sprintf('%s: %s in %s line %d', isset($this->levels[$level]) ? $this->levels[$level] : $level, $message, $file, $line), 0, $level, $file, $line);
        }

        return false;
    }

    public function handleFatall()
    {
        if (null === $error = error_get_last()) {
            return;
        }

        extract($error);

        if (0 === $this->level || !in_array($type, array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE))) {
            return;
        }

        // get current exception handler
        $exceptionHandler = set_exception_handler(function() {});
        restore_exception_handler();

        if (is_array($exceptionHandler) && $exceptionHandler[0] instanceof ExceptionHandler) {
            $exception = new FatalErrorException(sprintf('%s: %s in %s line %d', isset($this->levels[$type]) ? $this->levels[$type] : $type, $message, $file, $line), 0, $type, $file, $line);
            $exceptionHandler[0]->handle($exception);
        }
    }
}
