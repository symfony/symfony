<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encryption;

/**
 * Symmetric encryption uses the same key to encrypt and decrypt a message. The
 * keys should be kept safe and should not be exposed to the public. The key length
 * should be 32 bytes, but other sizes are accepted.
 *
 * Symmetric encryption is in theory weaker than asymmetric encryption.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @experimental in 5.2
 */
interface SymmetricEncryptionInterface
{
    public function encrypt(string $message): string;

    public function decrypt(string $message): string;
}
