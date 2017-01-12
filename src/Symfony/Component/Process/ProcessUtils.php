<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\InvalidArgumentException;

/**
 * ProcessUtils is a bunch of utility methods.
 *
 * This class contains static methods only and is not meant to be instantiated.
 */
class ProcessUtils
{
    const ESC_WINDOWS_ARGV = 1;
    const ESC_WINDOWS_CMD = 2;
    const ESC_WINDOWS_ARGV_CMD = 3;

    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Escapes a string to be used as a shell argument.
     *
     * Provides a more robust method on Windows than escapeshellarg.
     * See https://blogs.msdn.microsoft.com/twistylittlepassagesallalike/2011/04/23/everyone-quotes-command-line-arguments-the-wrong-way/
     *
     * @param string $argument The argument that will be escaped
     * @param int    $mode     A bitfield of self::ESC_WINDOWS_* constants to configure escaping context on Windows
     *
     * @return string The escaped argument
     */
    public static function escapeArgument($argument, $mode = self::ESC_WINDOWS_ARGV_CMD)
    {
        if ('\\' !== DIRECTORY_SEPARATOR) {
            return escapeshellarg($argument);
        }
        if (!(self::ESC_WINDOWS_ARGV_CMD & $mode)) {
            throw new \InvalidArgumentException(sprintf('The $mode argument of %s must be a non-zero bitfield of self::ESC_WINDOWS_* constants.', __METHOD__));
        }

        if (1 === func_num_args()) {
            $argument = preg_replace_callback("/!([^!=\n]++)!/", function ($m) {
                @trigger_error(sprintf('Delayed variables are deprecated since Symfony 3.3 and will be left unresolved in 4.0. Resolve the %s variable before calling ProcessUtil::escapeArgument().', $m[0]), E_USER_DEPRECATED);

                return getenv($m[1]);
            }, $argument);
        }

        if ((self::ESC_WINDOWS_ARGV & $mode) && (false !== strpbrk($argument, " \t\n\v\"") || !isset($argument[0]))) {
            $argument = preg_replace('/(\\\\*+)"/', '$1$1\\"', $argument);
            $argument = preg_replace('/(\\\\++)$/', '$1$1', $argument);
            $argument = '"'.$argument.'"';
        }

        if (self::ESC_WINDOWS_CMD & $mode) {
            $argument = preg_replace('/[()%!^"<>&|]/', '^$0', $argument);
        }

        return $argument;
    }

    /**
     * Validates and normalizes a Process input.
     *
     * @param string $caller The name of method call that validates the input
     * @param mixed  $input  The input to validate
     *
     * @return mixed The validated input
     *
     * @throws InvalidArgumentException In case the input is not valid
     */
    public static function validateInput($caller, $input)
    {
        if (null !== $input) {
            if (is_resource($input)) {
                return $input;
            }
            if (is_string($input)) {
                return $input;
            }
            if (is_scalar($input)) {
                return (string) $input;
            }
            if ($input instanceof Process) {
                return $input->getIterator($input::ITER_SKIP_ERR);
            }
            if ($input instanceof \Iterator) {
                return $input;
            }
            if ($input instanceof \Traversable) {
                return new \IteratorIterator($input);
            }

            throw new InvalidArgumentException(sprintf('%s only accepts strings, Traversable objects or stream resources.', $caller));
        }

        return $input;
    }
}
