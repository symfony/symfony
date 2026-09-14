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

use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * The frame carries a reference to a data key held by a {@see DataKeyStoreInterface}.
 *
 * Decrypting needs that store, which is the trade-off: an envelope is no longer self-sufficient,
 * and in exchange the KMS is contacted once per data key instead of once per payload, payloads
 * shrink to a 16-byte reference, and rewrapping the stored keys moves an entire dataset to another
 * master key, or to another provider, without reading a single payload.
 *
 * Layout: `[id][2-byte reference length][reference][iv][tag][ciphertext]`.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class StoredFormat extends EnvelopeFormat
{
    public const int ID = 2;

    public function id(): int
    {
        return self::ID;
    }

    public function keyBytes(): int
    {
        return 32;
    }

    public function ivBytes(): int
    {
        return 12;
    }

    public function tagBytes(): int
    {
        return 16;
    }

    public function cipher(): string
    {
        return 'aes-256-gcm';
    }

    /**
     * @throws LogicException If the envelope carries a wrapped data key instead of a reference
     */
    public function frame(Envelope $envelope): string
    {
        if (null === $envelope->reference) {
            throw new LogicException(\sprintf('An envelope framed as "%s" must carry a data key reference.', self::class));
        }

        return $this->head($envelope->reference).$envelope->iv.$envelope->tag.$envelope->ciphertext;
    }

    /**
     * @throws InvalidArgumentException If the frame is malformed
     */
    public function parse(string $bytes): Envelope
    {
        $length = \strlen($bytes);
        $offset = 1;

        $reference = self::readField($bytes, $offset, $length);
        [$iv, $tag, $ciphertext] = $this->readTail($bytes, $offset, $length);

        return Envelope::referencing($reference, $iv, $tag, $ciphertext);
    }
}
