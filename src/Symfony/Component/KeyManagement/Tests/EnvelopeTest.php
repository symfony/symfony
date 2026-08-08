<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\SelfContainedFormat;

class EnvelopeTest extends TestCase
{
    public function testToStringAndFromBytesRoundTrip()
    {
        $original = Envelope::selfContained(
            keyId: 'app-key',
            wrappedDek: str_repeat("\xAA", 50),
            iv: str_repeat("\xBB", 12),
            tag: str_repeat("\xCC", 16),
            ciphertext: 'opaque',
        );

        $reparsed = Envelope::fromBytes((string) $original);

        $this->assertSame($original->keyId, $reparsed->keyId);
        $this->assertSame($original->wrappedDek, $reparsed->wrappedDek);
        $this->assertSame($original->format->id(), $reparsed->format->id());
        $this->assertSame($original->iv, $reparsed->iv);
        $this->assertSame($original->tag, $reparsed->tag);
        $this->assertSame($original->ciphertext, $reparsed->ciphertext);
    }

    public function testStringableMatchesByteFraming()
    {
        $envelope = Envelope::selfContained('id', 'wrap', str_repeat('I', 12), str_repeat('T', 16), 'ct');

        $this->assertSame((string) $envelope, $envelope->__toString());
    }

    public function testFrameStartsWithVersionByte()
    {
        $envelope = Envelope::selfContained('id', 'wrap', str_repeat('I', 12), str_repeat('T', 16), 'ct');
        $bytes = (string) $envelope;

        $this->assertSame(SelfContainedFormat::ID, \ord($bytes[0]));
    }

    public static function provideMalformedBytes(): iterable
    {
        $valid = (string) Envelope::selfContained('app', str_repeat("\xAA", 32), str_repeat("\xBB", 12), str_repeat("\xCC", 16), 'data');

        yield 'empty' => [''];
        yield 'unknown version byte' => ["\xFF".substr($valid, 1)];
        yield 'truncated header' => [substr($valid, 0, 2)];
        yield 'keyId truncated' => [substr($valid, 0, 5)];
        yield 'wrapped dek truncated' => [substr($valid, 0, 1 + 2 + 3 + 2 + 10)];
        yield 'missing iv/tag' => [substr($valid, 0, -5)];
    }

    #[DataProvider('provideMalformedBytes')]
    public function testFromBytesRejectsMalformedFrames(string $bytes)
    {
        $this->expectException(InvalidArgumentException::class);
        Envelope::fromBytes($bytes);
    }

    public function testKeyIdLongerThan65535BytesIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"key id" is too long');
        Envelope::selfContained(
            keyId: str_repeat('a', 65536),
            wrappedDek: 'wrap',
            iv: str_repeat('I', 12),
            tag: str_repeat('T', 16),
            ciphertext: 'ct',
        );
    }

    public function testKeyIdAtTheBoundaryIsAccepted()
    {
        $envelope = Envelope::selfContained(
            keyId: str_repeat('a', 65535),
            wrappedDek: 'wrap',
            iv: str_repeat('I', 12),
            tag: str_repeat('T', 16),
            ciphertext: 'ct',
        );

        $this->assertSame(65535, \strlen(Envelope::fromBytes((string) $envelope)->keyId));
    }

    public static function provideIvAndTagOfTheWrongLength(): iterable
    {
        yield 'short iv' => ['iv', str_repeat('I', 11), str_repeat('T', 16)];
        yield 'long iv' => ['iv', str_repeat('I', 13), str_repeat('T', 16)];
        yield 'short tag' => ['tag', str_repeat('I', 12), str_repeat('T', 15)];
        yield 'long tag' => ['tag', str_repeat('I', 12), str_repeat('T', 17)];
    }

    #[DataProvider('provideIvAndTagOfTheWrongLength')]
    public function testSelfContainedRejectsAnIvOrTagOfTheWrongLength(string $field, string $iv, string $tag)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Envelope "%s" must be', $field));
        Envelope::selfContained('app', 'wrap', $iv, $tag, 'ct');
    }
}
