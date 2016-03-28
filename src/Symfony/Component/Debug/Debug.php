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

use Symfony\Component\ClassLoader\DebugClassLoader;

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
     *
     * If the Symfony ClassLoader component is available, a special
     * class loader is also registered.
     *
     * @param int  $errorReportingLevel The level of error reporting you want
     * @param bool $displayErrors       Whether to display errors (for development) or just log them (for production)
     */
    public static function enable($errorReportingLevel = null, $displayErrors = true)
    {
        if (static::$enabled) {
            return;
        }

        static::$enabled = true;

        error_reporting(-1);

        ErrorHandler::register($errorReportingLevel, $displayErrors);
        if ('cli' !== PHP_SAPI) {
            ExceptionHandler::register();

            if (PHP_VERSION_ID >= 70000) {
                $exceptionHandler = set_exception_handler(function ($throwable) use (&$exceptionHandler) {
                    if ($throwable instanceof \Exception) {
                        $exception = $throwable;
                    } else {
                        static $refl = null;

                        if (null === $refl) {
                            $refl = array();
                            foreach (array('file', 'line', 'trace') as $prop) {
                                $prop = new \ReflectionProperty('Exception', $prop);
                                $prop->setAccessible(true);
                                $refl[] = $prop;
                            }
                        }
                        $exception = new \Exception($throwable->getMessage(), $throwable->getCode());
                        foreach ($refl as $prop) {
                            $prop->setValue($exception, $throwable->{'get'.$prop->name}());
                        }
                    }
                    $exceptionHandler($exception);
                });
            }
        // CLI - display errors only if they're not already logged to STDERR
        } elseif ($displayErrors && (!ini_get('log_errors') || ini_get('error_log'))) {
            ini_set('display_errors', 1);
        }

        if (class_exists('Symfony\Component\ClassLoader\DebugClassLoader')) {
            DebugClassLoader::enable();
        }
    }
}
