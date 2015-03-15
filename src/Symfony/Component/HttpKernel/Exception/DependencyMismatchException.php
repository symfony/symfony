<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * DependencyMismatchException.
 *
 * For exceptions related to dependency issues, like missing or recursive dependencies.
 *
 * @author André Roemcke <andre.romcke@ez.no>
 *
 * @since 2.8
 */
class DependencyMismatchException extends \RuntimeException
{
    /**
     * Constructor.
     *
     * @param string     $msg      The message; for recursion issues, missing dependency, ..
     * @param array      $stack    The Bundle dependency stack trace up until the mismatch using bundle name or FQN
     * @param \Exception $previous The previous exception if there was one
     */
    public function __construct($msg, array $stack, \Exception $previous = null)
    {
        parent::__construct(
            sprintf("%s, bundle dependencies stack trace: '%s'", $msg, var_export($stack, true)),
            0,
            $previous
        );
    }
}
