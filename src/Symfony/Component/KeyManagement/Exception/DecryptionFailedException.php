<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Exception;

/**
 * Thrown when a ciphertext cannot be decrypted, for any reason. Implementations
 * MUST NOT leak the underlying cause through the exception message: an attacker
 * who can distinguish "wrong key" from "wrong AAD" from "tampered ciphertext"
 * can mount padding-oracle-style attacks.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class DecryptionFailedException extends RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Decryption failed.', 0, $previous);
    }
}
