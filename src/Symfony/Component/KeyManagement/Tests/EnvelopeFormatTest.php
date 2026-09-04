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
use Symfony\Component\KeyManagement\EnvelopeFormat;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\SelfContainedFormat;
use Symfony\Component\KeyManagement\StoredFormat;

class EnvelopeFormatTest extends TestCase
{
    public function testEachShippedIdResolvesToItsFormat()
    {
        $this->assertInstanceOf(SelfContainedFormat::class, EnvelopeFormat::fromId(SelfContainedFormat::ID));
        $this->assertInstanceOf(StoredFormat::class, EnvelopeFormat::fromId(StoredFormat::ID));
    }

    public function testAnUnknownIdIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported envelope format (id 0x2a).');
        EnvelopeFormat::fromId(42);
    }

    public function testTheTwoShippedFormatsDoNotShareAnId()
    {
        $this->assertNotSame(SelfContainedFormat::ID, StoredFormat::ID);
    }

    /**
     * @return iterable<string, array{EnvelopeFormat}>
     */
    public static function provideShippedFormats(): iterable
    {
        yield 'self-contained' => [new SelfContainedFormat()];
        yield 'stored' => [new StoredFormat()];
    }

    #[DataProvider('provideShippedFormats')]
    public function testTheFrameStartsWithTheFormatId(EnvelopeFormat $format)
    {
        $bytes = (string) self::envelopeFor($format);

        $this->assertSame($format->id(), \ord($bytes[0]));
    }

    #[DataProvider('provideShippedFormats')]
    public function testAFrameRoundTripsThroughTheFormatThatWroteIt(EnvelopeFormat $format)
    {
        $original = self::envelopeFor($format);

        $reparsed = Envelope::fromBytes((string) $original);

        $this->assertSame($format->id(), $reparsed->format->id());
        $this->assertSame($original->keyId, $reparsed->keyId);
        $this->assertSame($original->wrappedDek, $reparsed->wrappedDek);
        $this->assertSame($original->reference, $reparsed->reference);
        $this->assertSame($original->iv, $reparsed->iv);
        $this->assertSame($original->tag, $reparsed->tag);
        $this->assertSame($original->ciphertext, $reparsed->ciphertext);
    }

    public function testAStoredFrameCarriesOnlyItsReference()
    {
        $envelope = self::envelopeFor(new StoredFormat());

        $this->assertNull($envelope->keyId);
        $this->assertNull($envelope->wrappedDek);
        $this->assertSame(str_repeat("\x11", 16), $envelope->reference);
    }

    public function testAStoredFrameIsShorterThanASelfContainedOne()
    {
        $stored = \strlen((string) self::envelopeFor(new StoredFormat()));
        $selfContained = \strlen((string) self::envelopeFor(new SelfContainedFormat()));

        $this->assertLessThan($selfContained, $stored, 'referring to a stored key is what makes payloads smaller.');
    }

    public function testTheReferenceLengthFieldIsEnforced()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"data key reference" is too long');
        Envelope::referencing(str_repeat('r', 65536), str_repeat('I', 12), str_repeat('T', 16), 'ct');
    }

    public static function provideMalformedStoredFrames(): iterable
    {
        $valid = (string) self::envelopeFor(new StoredFormat());

        yield 'truncated length field' => [substr($valid, 0, 2)];
        yield 'reference truncated' => [substr($valid, 0, 1 + 2 + 8)];
        yield 'missing iv and tag' => [substr($valid, 0, 1 + 2 + 16 + 20)];
    }

    #[DataProvider('provideMalformedStoredFrames')]
    public function testAMalformedStoredFrameIsRejected(string $bytes)
    {
        $this->expectException(InvalidArgumentException::class);
        Envelope::fromBytes($bytes);
    }

    private static function envelopeFor(EnvelopeFormat $format): Envelope
    {
        $iv = str_repeat("\xBB", $format->ivBytes());
        $tag = str_repeat("\xCC", $format->tagBytes());

        return $format instanceof StoredFormat
            ? Envelope::referencing(str_repeat("\x11", 16), $iv, $tag, 'opaque')
            : Envelope::selfContained('app-key', str_repeat("\xAA", 50), $iv, $tag, 'opaque');
    }
}
