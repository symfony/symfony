<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Exception;

/**
 * Thrown when a message cannot be encrypted.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 6.0
 */
class EncryptionException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $message = null, \Throwable $previous = null)
    {
        parent::__construct($message ?? 'Could not encrypt the message.', 0, $previous);
    }
}
