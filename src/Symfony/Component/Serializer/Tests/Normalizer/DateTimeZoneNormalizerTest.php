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

    protected function setUp()
    {
        $this->normalizer = new DateTimeZoneNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new \DateTimeZone('UTC')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \DateTimeImmutable()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $this->assertEquals('UTC', $this->normalizer->normalize(new \DateTimeZone('UTC')));
        $this->assertEquals('Asia/Tokyo', $this->normalizer->normalize(new \DateTimeZone('Asia/Tokyo')));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testNormalizeBadObjectTypeThrowsException()
    {
        $this->normalizer->normalize(new \stdClass());
    }

    public function testSupportsDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization(null, \DateTimeZone::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \DateTimeImmutable::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    public function testDenormalize()
    {
        $this->assertEquals(new \DateTimeZone('UTC'), $this->normalizer->denormalize('UTC', \DateTimeZone::class, null));
        $this->assertEquals(new \DateTimeZone('Asia/Tokyo'), $this->normalizer->denormalize('Asia/Tokyo', \DateTimeZone::class, null));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\NotNormalizableValueException
     */
    public function testDenormalizeNullTimeZoneThrowsException()
    {
        $this->normalizer->denormalize(null, \DateTimeZone::class, null);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\NotNormalizableValueException
     */
    public function testDenormalizeBadTimeZoneThrowsException()
    {
        $this->normalizer->denormalize('Jupiter/Europa', \DateTimeZone::class, null);
    }
}
