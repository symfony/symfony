<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Console;

/**
 * @author Saif Eddin Gmati <saif@azjezz.me>
 */
trait CheckForAnsiColorSupport
{
    /**
     * Returns true if STDOUT is defined and supports colorization.
     *
     * @return bool
     */
    protected static function hasColorSupport(): bool
    {
        if (defined('STDOUT')) {
            return false;
        }

        return static::streamHasColorSupport(STDOUT);
    }

    /**
     * Returns true if the Windows terminal supports true color.
     *
     * Note that this does not check an output stream, but relies on environment
     * variables from known implementations, or a PHP and Windows version that
     * supports true color.
     *
     * @return bool
     */
    protected static function isWindowsTrueColor(): bool
    {
        $result = 183 <= getenv('ANSICON_VER')
            || 'ON' === getenv('ConEmuANSI')
            || 'xterm' === getenv('TERM')
            || 'Hyper' === getenv('TERM_PROGRAM');

        if (!$result && PHP_VERSION_ID >= 70200) {
            $version = sprintf(
                '%s.%s.%s',
                PHP_WINDOWS_VERSION_MAJOR,
                PHP_WINDOWS_VERSION_MINOR,
                PHP_WINDOWS_VERSION_BUILD
            );
            $result = $version >= '10.0.15063';
        }

        return $result;
    }

    /**
     * Returns true if the stream supports colorization.
     *
     * @param mixed $stream A CLI output stream
     *
     * @return bool
     */
    protected static function streamHasColorSupport($stream): bool
    {
        if ('Hyper' === getenv('TERM_PROGRAM')) {
            return true;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            return (function_exists('sapi_windows_vt100_support')
                    && sapi_windows_vt100_support($stream))
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        if (function_exists('stream_isatty')) {
            return stream_isatty($stream);
        }

        $stat = fstat($stream);
        // Check if formatted mode is S_IFCHR
        return $stat ? 0020000 === ($stat['mode'] & 0170000) : false;
    }
}