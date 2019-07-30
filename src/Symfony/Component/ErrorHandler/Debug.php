<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler;

/**
 * Registers all the debug tools.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Debug
{
    private static $enabled = false;

    /**
     * Enables the debug tools.
     *
     * This method registers an error handler and an exception handler.
     */
    public static function enable(int $errorReportingLevel = E_ALL, bool $displayErrors = true): void
    {
        if (static::$enabled) {
            return;
        }

        static::$enabled = true;

        if (null !== $errorReportingLevel) {
            error_reporting($errorReportingLevel);
        } else {
            error_reporting(E_ALL);
        }

        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            ini_set('display_errors', 0);
        } elseif ($displayErrors && (!filter_var(ini_get('log_errors'), FILTER_VALIDATE_BOOLEAN) || ini_get('error_log'))) {
            // CLI - display errors only if they're not already logged to STDERR
            ini_set('display_errors', 1);
        }
        if ($displayErrors) {
            ErrorHandler::register(new ErrorHandler(new BufferingLogger()));
        } else {
            ErrorHandler::register()->throwAt(0, true);
        }

        DebugClassLoader::enable();
    }
}
