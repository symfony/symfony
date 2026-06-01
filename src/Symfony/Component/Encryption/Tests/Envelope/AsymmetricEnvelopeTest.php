<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Tests\Envelope;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Envelope\AsymmetricEnvelope;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;

final class AsymmetricEnvelopeTest extends TestCase
{
    public function testAnonymousRoundTrip(): void
    {
        $body = random_bytes(58);

        $envelope = AsymmetricEnvelope::deserialize(AsymmetricEnvelope::anonymous('x25519', $body)->serialize());

        self::assertSame(AsymmetricEnvelope::MODE_ANONYMOUS, $envelope->mode());
        self::assertSame('x25519', $envelope->algorithm());
        self::assertSame($body, $envelope->body());
        self::assertSame('', $envelope->nonce());
    }

    public function testAuthenticatedRoundTrip(): void
    {
        $nonce = random_bytes(24);
        $body = random_bytes(40);

        $envelope = AsymmetricEnvelope::deserialize(AsymmetricEnvelope::authenticated('x25519', $nonce, $body)->serialize());

        self::assertSame(AsymmetricEnvelope::MODE_AUTHENTICATED, $envelope->mode());
        self::assertSame($nonce, $envelope->nonce());
        self::assertSame($body, $envelope->body());
    }

    public function testFormatBeginsWithMagic(): void
    {
        $raw = AsymmetricEnvelope::anonymous('x25519', random_bytes(58))->serialize();

        self::assertSame('SYA', substr($raw, 0, 3));
        self::assertSame(1, \ord($raw[3]));
    }

    public function testRejectsBadMagic(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AsymmetricEnvelope::deserialize('ZZZ' . str_repeat("\x00", 40));
    }

    public function testRejectsUnsupportedVersion(): void
    {
        $raw = AsymmetricEnvelope::anonymous('x25519', random_bytes(58))->serialize();
        $raw[3] = "\x02";

        $this->expectException(InvalidArgumentException::class);

        AsymmetricEnvelope::deserialize($raw);
    }

    public function testRejectsUnsupportedAlgorithm(): void
    {
        $raw = AsymmetricEnvelope::anonymous('x25519', random_bytes(58))->serialize();
        $raw[4] = "\x7f";

        $this->expectException(InvalidArgumentException::class);

        AsymmetricEnvelope::deserialize($raw);
    }

    public function testRejectsUnsupportedMode(): void
    {
        $raw = AsymmetricEnvelope::anonymous('x25519', random_bytes(58))->serialize();
        $raw[5] = "\x09";

        $this->expectException(InvalidArgumentException::class);

        AsymmetricEnvelope::deserialize($raw);
    }

    public function testRejectsTruncatedAuthenticatedEnvelope(): void
    {
        $raw = AsymmetricEnvelope::authenticated('x25519', random_bytes(24), random_bytes(40))->serialize();

        $this->expectException(InvalidArgumentException::class);

        AsymmetricEnvelope::deserialize(substr($raw, 0, 10));
    }

    public function testRsaAnonymousRoundTrip(): void
    {
        $body = random_bytes(400);

        $envelope = AsymmetricEnvelope::deserialize(AsymmetricEnvelope::anonymous('rsa', $body)->serialize());

        self::assertSame('rsa', $envelope->algorithm());
        self::assertSame(AsymmetricEnvelope::MODE_ANONYMOUS, $envelope->mode());
        self::assertSame($body, $envelope->body());
    }
}
