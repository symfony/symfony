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

/**
 * Opaque container for encrypted data returned by a KMS, paired with the key
 * identifier needed to decrypt it.
 *
 * The `$blob` is the raw, backend-specific ciphertext (with whatever framing
 * the backend uses internally: nonce, version byte, key version, ...). Callers
 * MUST treat it as opaque bytes and pass it back unchanged on decryption.
 *
 * The `$keyId` is required: persisting a ciphertext without remembering which
 * key produced it is a footgun. Some backends embed the key reference inside
 * the blob (e.g. AWS KMS) but still need a logical reference for routing,
 * auditing or auth scoping.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class Ciphertext implements \Stringable
{
    public function __construct(
        public readonly string $blob,
        public readonly string $keyId,
    ) {
    }

    public function __toString(): string
    {
        return $this->blob;
    }
}
