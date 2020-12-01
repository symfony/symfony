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
 * The sender requires you to verify the signature. You should pass both your
 * private key and the senders public key to the decrypt() method.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 6.0
 */
class SignatureVerificationRequiredException extends DecryptionException
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('The sender requires you to verify the signature.');
    }
}
