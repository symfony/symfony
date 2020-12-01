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
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 6.0
 */
class UnableToVerifySignatureException extends DecryptionException
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('The origin of the message could not be verified.', $previous);
    }
}
