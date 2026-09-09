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

/**
 * Decrypts an {@see Envelope} produced by an
 * {@see EnvelopeEncrypterInterface}.
 *
 * The same `$aad` bytes used at encrypt time MUST be supplied here,
 * otherwise decryption fails.
 *
 * An envelope naming a key unknown to the underlying KMS is reported as a
 * {@see DecryptionFailedException}, deliberately indistinguishable from
 * tampering.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface EnvelopeDecrypterInterface
{
    /**
     * @throws DecryptionFailedException If the envelope is invalid, tampered, names an unknown key, or `$aad` does not match
     */
    public function decrypt(Envelope $envelope, string $aad = ''): string;
}
