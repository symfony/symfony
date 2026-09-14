<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\Serializer\EnvelopeNormalizer;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class EnvelopeNormalizerTest extends TestCase
{
    private EnvelopeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new EnvelopeNormalizer();
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([Envelope::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testSupportsOnlyEnvelopes()
    {
        $envelope = self::makeEnvelope();

        $this->assertTrue($this->normalizer->supportsNormalization($envelope));
        $this->assertFalse($this->normalizer->supportsNormalization(new Ciphertext('blob', 'app')));
        $this->assertFalse($this->normalizer->supportsNormalization('plain string'));

        $this->assertTrue($this->normalizer->supportsDenormalization('whatever', Envelope::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('whatever', Ciphertext::class));
    }

    public function testNormalizeProducesBase64String()
    {
        $envelope = self::makeEnvelope();

        $normalized = $this->normalizer->normalize($envelope);

        $this->assertSame(base64_encode((string) $envelope), $normalized);
    }

    public function testDenormalizeRoundTrip()
    {
        $envelope = self::makeEnvelope();
        $normalized = $this->normalizer->normalize($envelope);

        $recovered = $this->normalizer->denormalize($normalized, Envelope::class);

        $this->assertSame($envelope->keyId, $recovered->keyId);
        $this->assertSame($envelope->wrappedDek, $recovered->wrappedDek);
        $this->assertSame($envelope->iv, $recovered->iv);
        $this->assertSame($envelope->tag, $recovered->tag);
        $this->assertSame($envelope->ciphertext, $recovered->ciphertext);
    }

    public function testNormalizeRejectsAnythingButAnEnvelope()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(new Ciphertext('blob', 'app'));
    }

    public function testDenormalizeRejectsNonStringInput()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(['not', 'a', 'string'], Envelope::class);
    }

    public function testDenormalizeRejectsInvalidBase64()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize('$$$not-base64$$$', Envelope::class);
    }

    public function testDenormalizeRejectsMalformedFrame()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(base64_encode('not-an-envelope'), Envelope::class);
    }

    private static function makeEnvelope(): Envelope
    {
        return Envelope::selfContained(
            keyId: 'app-key',
            wrappedDek: random_bytes(48),
            iv: random_bytes(12),
            tag: random_bytes(16),
            ciphertext: random_bytes(64),
        );
    }
}
