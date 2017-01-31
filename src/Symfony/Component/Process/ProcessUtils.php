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
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Escapes a string to be used as a shell argument.
     *
     * @param string $argument The argument that will be escaped
     *
     * @return string The escaped argument
     */
    public static function escapeArgument($argument)
    {
        if ('\\' !== DIRECTORY_SEPARATOR) {
            return escapeshellarg($argument);
        }
        $argument = preg_replace_callback("/!([^!=\n]++)!/", function ($m) {
            if (false !== $v = getenv($m[1])) {
                @trigger_error(sprintf('Delayed variables are deprecated since Symfony 3.3 and will be left unresolved in 4.0. Resolve the %s variable before calling ProcessUtils::escapeArgument().', $m[0]), E_USER_DEPRECATED);

                return $v;
            }

            return $m[0];
        }, $argument);
        if ('' === $argument) {
            return '""';
        }
        if (false !== strpos($argument, "\0")) {
            $argument = str_replace("\0", '?', $argument);
        }
        if (!preg_match('/[()%!^"<>&|\s]/', $argument)) {
            return $argument;
        }
        $argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);

        return '"'.str_replace(array('"', '^', '%', '!', "\n"), array('""', '"^^"', '"^%"', '"^!"', '!LF!'), $argument).'"';
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

    private static function isSurroundedBy($arg, $char)
    {
        return 2 < strlen($arg) && $char === $arg[0] && $char === $arg[strlen($arg) - 1];
    }
}
