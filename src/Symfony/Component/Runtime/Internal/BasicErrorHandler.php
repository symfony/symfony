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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class BasicErrorHandler
{
    public static function register(bool $debug): void
    {
        error_reporting(-1);

        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            ini_set('display_errors', $debug);
        } elseif (!filter_var(\ini_get('log_errors'), \FILTER_VALIDATE_BOOL) || \ini_get('error_log')) {
            // CLI - display errors only if they're not already logged to STDERR
            ini_set('display_errors', 1);
        }

        if (0 <= \ini_get('zend.assertions')) {
            ini_set('zend.assertions', 1);
            ini_set('assert.active', $debug);
            ini_set('assert.exception', 1);
        }

        set_error_handler(new self());
    }

    public function __invoke(int $type, string $message, string $file, int $line): bool
    {
        if ((\E_DEPRECATED | \E_USER_DEPRECATED) & $type) {
            return true;
        }

        if ((error_reporting() | \E_ERROR | \E_RECOVERABLE_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR) & $type) {
            throw new \ErrorException($message, 0, $type, $file, $line);
        }

        return false;
    }
}
