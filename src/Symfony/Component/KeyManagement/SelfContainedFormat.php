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
 * The frame carries its own wrapped data key, next to the master key that wrapped it.
 *
 * Nothing but the KMS is needed to decrypt, which makes an envelope in this format entirely
 * self-sufficient: store it anywhere, hand it to anyone holding the master key. The price is one
 * KMS call per payload, a couple hundred bytes of wrapped key in every payload, and a master key
 * that can no longer be rotated without rewriting the payloads that refer to it.
 *
 * Layout: `[id][2-byte keyId length][keyId][2-byte wrappedDek length][wrappedDek][iv][tag][ciphertext]`.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class SelfContainedFormat extends EnvelopeFormat
{
    public const int ID = 1;

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
     * @throws LogicException If the envelope holds a data key reference instead of a wrapped key
     */
    public function frame(Envelope $envelope): string
    {
        if (null === $envelope->keyId || null === $envelope->wrappedDek) {
            throw new LogicException(\sprintf('An envelope framed as "%s" must carry its wrapped data key.', self::class));
        }

        return $this->head($envelope->keyId, $envelope->wrappedDek).$envelope->iv.$envelope->tag.$envelope->ciphertext;
    }

    /**
     * @throws InvalidArgumentException If the frame is malformed
     */
    public function parse(string $bytes): Envelope
    {
        $length = \strlen($bytes);
        $offset = 1;

        $keyId = self::readField($bytes, $offset, $length);
        $wrappedDek = self::readField($bytes, $offset, $length);
        [$iv, $tag, $ciphertext] = $this->readTail($bytes, $offset, $length);

        return Envelope::selfContained($keyId, $wrappedDek, $iv, $tag, $ciphertext);
    }
}
