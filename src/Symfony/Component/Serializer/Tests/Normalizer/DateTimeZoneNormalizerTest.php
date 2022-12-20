<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;

/**
 * @author Jérôme Desjardins <jewome62@gmail.com>
 */
class DateTimeZoneNormalizerTest extends TestCase
{
    /**
     * @var DateTimeZoneNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DateTimeZoneNormalizer();
    }

    public function testSupportsNormalization()
    {
        self::assertTrue($this->normalizer->supportsNormalization(new \DateTimeZone('UTC')));
        self::assertFalse($this->normalizer->supportsNormalization(new \DateTimeImmutable()));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        self::assertEquals('UTC', $this->normalizer->normalize(new \DateTimeZone('UTC')));
        self::assertEquals('Asia/Tokyo', $this->normalizer->normalize(new \DateTimeZone('Asia/Tokyo')));
    }

    public function testNormalizeBadObjectTypeThrowsException()
    {
        self::expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(new \stdClass());
    }

    public function testSupportsDenormalization()
    {
        self::assertTrue($this->normalizer->supportsDenormalization(null, \DateTimeZone::class));
        self::assertFalse($this->normalizer->supportsDenormalization(null, \DateTimeImmutable::class));
        self::assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    public function testDenormalize()
    {
        self::assertEquals(new \DateTimeZone('UTC'), $this->normalizer->denormalize('UTC', \DateTimeZone::class, null));
        self::assertEquals(new \DateTimeZone('Asia/Tokyo'), $this->normalizer->denormalize('Asia/Tokyo', \DateTimeZone::class, null));
    }

    public function testDenormalizeNullTimeZoneThrowsException()
    {
        self::expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(null, \DateTimeZone::class, null);
    }

    public function testDenormalizeBadTimeZoneThrowsException()
    {
        self::expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize('Jupiter/Europa', \DateTimeZone::class, null);
    }
}
