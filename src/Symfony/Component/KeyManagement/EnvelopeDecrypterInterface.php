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

use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\UnexpectedEnvelopeException;

/**
 * Decrypts an {@see Envelope} produced by an {@see EnvelopeEncrypterInterface}.
 *
 * The same `$aad` bytes used at encrypt time MUST be supplied here,
 * otherwise decryption fails.
 *
 * `$key` is what the payload is expected to have been written under, read as
 * {@see EnvelopeEncrypterInterface::encrypt()} reads its own: a master key id, or a scope. An
 * envelope names the data key that opens it, in bytes whoever wrote the column chose, so a whole
 * and valid payload moved to another column, tenant or scope opens there unless the reader states
 * what it expects, from somewhere other than the envelope.
 *
 * An envelope naming a key unknown to the underlying KMS is reported as a
 * {@see DecryptionFailedException}, deliberately indistinguishable from
 * tampering.
 *
 * An envelope referring to a stored data key is a different case: the
 * reference is an operational identifier, not a secret, and a store that no
 * longer holds it reports it as a {@see DataKeyNotFoundException} naming the
 * reference so the row can be tracked down. An implementation given an
 * envelope in a format it cannot resolve at all refuses it with a
 * {@see LogicException} rather than reporting a decryption failure.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface EnvelopeDecrypterInterface
{
    /**
     * @param string|null $key What the payload is expected to have been written under, `null` to state no expectation
     *
     * @throws DecryptionFailedException   If the envelope is invalid, tampered, names an unknown key, or `$aad` does not match
     * @throws DataKeyNotFoundException    If the envelope refers to a stored data key that the store no longer holds
     * @throws UnexpectedEnvelopeException If the envelope was written under something other than `$key`
     * @throws LogicException              If the envelope is in a format this decrypter has no means to resolve
     */
    public function decrypt(Envelope $envelope, string $aad = '', ?string $key = null): string;
}
