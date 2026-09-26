<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip;

use Symfony\Component\KeyManagement\Ciphertext;

/**
 * Defines encryption and decryption with a server-held KMIP object.
 *
 * The bridge persists the scheme name in its ciphertext header and passes the header together with caller-provided AAD to both operations. The {@see Ciphertext} objects exchanged with implementations carry a key ID and a scheme-specific blob without the bridge's header.
 *
 * A scheme must authenticate all supplied associated data and reject modified ciphertext. Its name must remain mapped to the same algorithm and mode while ciphertext written with it is stored. A scheme can change a parameter only if it records enough information in each ciphertext to decrypt data written before the change.
 *
 * Plaintext, associated data and ciphertext can contain sensitive values. Implementations must mark those parameters as sensitive too, since attributes on this interface do not redact arguments in implementation stack frames.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
interface KmipEncryptionSchemeInterface
{
    public function name(): string;

    /**
     * Returns scheme-specific ciphertext without the bridge's format header.
     */
    public function encrypt(KmipRequestClientInterface $client, string $keyId, #[\SensitiveParameter] string $plaintext, #[\SensitiveParameter] string $aad): Ciphertext;

    /**
     * Receives scheme-specific ciphertext without the bridge's format header.
     */
    public function decrypt(KmipRequestClientInterface $client, #[\SensitiveParameter] Ciphertext $ciphertext, #[\SensitiveParameter] string $aad): string;
}
