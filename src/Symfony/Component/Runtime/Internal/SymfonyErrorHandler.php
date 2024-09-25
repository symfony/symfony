<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Internal;

use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class SymfonyErrorHandler
{
    public static function register(bool $debug): void
    {
        if (!class_exists(ErrorHandler::class)) {
            BasicErrorHandler::register($debug);

            return;
        }

        error_reporting(-1);

        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            ini_set('display_errors', $debug);
        } elseif (!filter_var(\ini_get('log_errors'), \FILTER_VALIDATE_BOOL) || \ini_get('error_log')) {
            // CLI - display errors only if they're not already logged to STDERR
            ini_set('display_errors', 1);
        }

        if (0 <= \ini_get('zend.assertions')) {
            ini_set('zend.assertions', (int) $debug);
        }
        ini_set('assert.active', 1);
        ini_set('assert.exception', 1);

        if ($debug) {
            DebugClassLoader::enable();
        }

        ErrorHandler::register(new ErrorHandler(new BufferingLogger(), $debug));
    }
}
