<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Exception;

use Throwable;

/**
 * Represents failure to read input from stdin.
 *
 * @author Gabriel Ostrolucký <gabriel.ostrolucky@gmail.com>
 */
class MissingInputException extends RuntimeException implements ExceptionInterface
{
    public function __construct($message = '', $code = 14, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
