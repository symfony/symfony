<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\FatalErrorHandler;

use Symfony\Component\Debug\Exception\FatalErrorException;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.4, use "%s" instead.', FatalErrorHandlerInterface::class, \Symfony\Component\ErrorHandler\FatalErrorHandler\FatalErrorHandlerInterface::class), \E_USER_DEPRECATED);

/**
 * Attempts to convert fatal errors to exceptions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 4.4, use Symfony\Component\ErrorHandler\FatalErrorHandler\FatalErrorHandlerInterface instead.
 */
interface FatalErrorHandlerInterface
{
    /**
     * Attempts to convert an error into an exception.
     *
     * @param array $error An array as returned by error_get_last()
     *
     * @return FatalErrorException|null A FatalErrorException instance if the class is able to convert the error, null otherwise
     */
    public function handleError(array $error, FatalErrorException $exception);
}
