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
use Symfony\Component\Debug\Exception\OutOfMemoryException;

/**
 * ErrorHandler for out of memory errors.
 *
 * @author error56 <nalldev2@gmail.com>
 */
class OutOfMemoryFatalErrorHandler implements FatalErrorHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handleError(array $error, FatalErrorException $exception)
    {
        if (false !== strpos($error['message'], 'memory')) {
            $message = 'I didn\'t got enough memory for execution :\/';
            
            return new OutOfMemoryException($message, $exception);
        }
    }
}
