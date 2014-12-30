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
 *
 * @author Martin Hasoň <martin.hason@gmail.com>
 *
 * @internal
 * @deprecated Deprecated as of Symfony 2.6, to be removed in symfony 3.0
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
     *
     * @deprecated Deprecated as of Symfony 2.6, to be removed in symfony 3.0
     */
    public static function escapeArgument($argument)
    {
        return Command::escape($argument);
    }

    /**
     * Validates and normalizes a Process input.
     *
     * @param string $caller The name of method call that validates the input
     * @param mixed  $input  The input to validate
     *
     * @return string The validated input
     *
     * @throws InvalidArgumentException In case the input is not valid
     */
    public static function validateInput($caller, $input)
    {
        if (null !== $input) {
            if (is_resource($input)) {
                return $input;
            }
            if (is_scalar($input)) {
                return (string) $input;
            }
            // deprecated as of Symfony 2.5, to be removed in 3.0
            if (is_object($input) && method_exists($input, '__toString')) {
                return (string) $input;
            }

            throw new InvalidArgumentException(sprintf('%s only accepts strings or stream resources.', $caller));
        }

        return $input;
    }
}
