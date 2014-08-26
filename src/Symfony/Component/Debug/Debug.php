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

use Symfony\Component\VarDumper\Cloner\ExtCloner;
use Symfony\Component\VarDumper\Cloner\PhpCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * Registers all the debug tools.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Debug
{
    private static $enabled = false;
    private static $dumpHandler;

    /**
     * Enables the debug tools.
     *
     * This method registers an error handler and an exception handler.
     *
     * If the Symfony ClassLoader component is available, a special
     * class loader is also registered.
     *
     * @param int     $errorReportingLevel The level of error reporting you want
     * @param bool    $displayErrors       Whether to display errors (for development) or just log them (for production)
     */
    public static function enable($errorReportingLevel = null, $displayErrors = true)
    {
        if (static::$enabled) {
            return;
        }

        static::$enabled = true;

        if (null !== $errorReportingLevel) {
            error_reporting($errorReportingLevel);
        } else {
            error_reporting(-1);
        }

        if ('cli' !== php_sapi_name()) {
            ini_set('display_errors', 0);
            ExceptionHandler::register();
        } elseif ($displayErrors && (!ini_get('log_errors') || ini_get('error_log'))) {
            // CLI - display errors only if they're not already logged to STDERR
            ini_set('display_errors', 1);
        }
        $handler = ErrorHandler::register();
        if (!$displayErrors) {
            $handler->throwAt(0, true);
        }

        DebugClassLoader::enable();
    }

    public static function dump($var)
    {
        if (null === self::$dumpHandler) {
            $cloner = extension_loaded('symfony_debug') ? new ExtCloner() : new PhpCloner();
            $dumper = 'cli' === PHP_SAPI ? new CliDumper() : new HtmlDumper();
            self::$dumpHandler = function ($var) use ($cloner, $dumper) {
                $dumper->dump($cloner->cloneVar($var));
            };
        }

        $h = self::$dumpHandler;

        if (is_array($h)) {
            return $h[0]->{$h[1]}($var);
        }

        return $h($var);
    }

    public static function setDumpHandler($callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Invalid PHP callback.');
        }

        $prevHandler = self::$dumpHandler;

        if (is_array($callable)) {
            if (!is_object($callable[0])) {
                self::$dumpHandler = $callable[0].'::'.$callable[1];
            }
        } else {
            self::$dumpHandler = $callable;
        }

        return $prevHandler;
    }
}
