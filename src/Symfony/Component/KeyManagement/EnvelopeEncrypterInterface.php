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

use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

/**
 * Encrypts arbitrary-size payloads into an {@see Envelope}.
 *
 * The implementation obtains a data key, encrypts the bulk payload locally
 * with an AEAD primitive, and bundles everything into the envelope. The same
 * `$aad` value MUST be supplied at decrypt time when given at encrypt time.
 *
 * `$key` names whatever selects the data key, which each implementation
 * defines: {@see EnvelopeEncrypter} reads it as the master key that wraps a
 * data key minted for this payload alone, while
 * {@see StoredEnvelopeEncrypter} reads it as the scope whose stored data key
 * is shared by every payload of that scope.
 *
 * Most implementations also implement {@see EnvelopeDecrypterInterface};
 * write-only deployments may expose only the encrypting half.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface EnvelopeEncrypterInterface
{
    /**
     * @throws KeyNotFoundException          If `$key` is unknown to the underlying KMS
     * @throws UnsupportedOperationException If `$aad` is non-empty and a backend cannot enforce it
     * @throws RuntimeException              On any other backend failure
     */
    public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope;
}
