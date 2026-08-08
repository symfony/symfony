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
     * @throws DecryptionFailedException If the envelope is invalid, tampered, names an unknown key, or `$aad` does not match
     * @throws DataKeyNotFoundException  If the envelope refers to a stored data key that the store no longer holds
     * @throws LogicException            If the envelope is in a format this decrypter has no means to resolve
     */
    public function decrypt(Envelope $envelope, string $aad = ''): string;
}
