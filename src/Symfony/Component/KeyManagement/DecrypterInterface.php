<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

/**
 * Decrypts data previously produced by an {@see EncrypterInterface}.
 *
 * AAD handling matches {@see EncrypterInterface}: the same opaque bytes
 * supplied at encryption time MUST be passed back here, otherwise
 * decryption fails.
 *
 * A ciphertext naming a key unknown to the backend is reported as a
 * {@see DecryptionFailedException}, deliberately indistinguishable from
 * tampering.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface DecrypterInterface
{
    /**
     * @throws DecryptionFailedException     If the ciphertext is invalid, tampered, names an unknown key, or `$aad` does not match
     * @throws UnsupportedOperationException If `$aad` is non-empty and the backend cannot enforce it
     */
    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string;
}
