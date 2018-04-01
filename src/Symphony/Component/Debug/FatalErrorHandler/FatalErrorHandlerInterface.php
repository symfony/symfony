<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Debug\FatalErrorHandler;

use Symphony\Component\Debug\Exception\FatalErrorException;

/**
 * Attempts to convert fatal errors to exceptions.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface FatalErrorHandlerInterface
{
    /**
     * Attempts to convert an error into an exception.
     *
     * @param array               $error     An array as returned by error_get_last()
     * @param FatalErrorException $exception A FatalErrorException instance
     *
     * @return FatalErrorException|null A FatalErrorException instance if the class is able to convert the error, null otherwise
     */
    public function handleError(array $error, FatalErrorException $exception);
}
