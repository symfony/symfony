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
    public static function enable($errorReportingLevel = null)
    {
        error_reporting(-1);
        ini_set('display_errors', 0);

        ErrorHandler::register($errorReportingLevel);
        if ('cli' !== php_sapi_name()) {
            ExceptionHandler::register();
        } elseif (!ini_get('log_errors') || ini_get('error_log')) {
            ini_set('display_errors', 1);
        }

        if (class_exists('Symfony\Component\ClassLoader\DebugClassLoader')) {
            DebugClassLoader::enable();
        }
    }
}
