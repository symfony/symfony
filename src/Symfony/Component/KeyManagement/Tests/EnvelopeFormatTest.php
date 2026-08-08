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

class EnvelopeFormatTest extends TestCase
{
    public function testEachShippedIdResolvesToItsFormat()
    {
        $this->assertInstanceOf(SelfContainedFormat::class, EnvelopeFormat::fromId(SelfContainedFormat::ID));
    }

    public function testAnUnknownIdIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported envelope format (id 0x2a).');
        EnvelopeFormat::fromId(42);
    }

    /**
     * @return iterable<string, array{EnvelopeFormat}>
     */
    public static function provideShippedFormats(): iterable
    {
        yield 'self-contained' => [new SelfContainedFormat()];
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
        $this->assertSame($original->iv, $reparsed->iv);
        $this->assertSame($original->tag, $reparsed->tag);
        $this->assertSame($original->ciphertext, $reparsed->ciphertext);
    }

    private static function envelopeFor(EnvelopeFormat $format): Envelope
    {
        $iv = str_repeat("\xBB", $format->ivBytes());
        $tag = str_repeat("\xCC", $format->tagBytes());

        return Envelope::selfContained('app-key', str_repeat("\xAA", 50), $iv, $tag, 'opaque');
    }
}
