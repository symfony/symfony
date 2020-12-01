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
 * Thrown when a message cannot be decrypted.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 6.0
 */
class DecryptionException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $message = null, \Throwable $previous = null)
    {
        parent::__construct($message ?? 'Could not decrypt the ciphertext.', 0, $previous);
    }
}
