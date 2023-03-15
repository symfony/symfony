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
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\IntegerBackedEnumDummy;
use Symfony\Component\Serializer\Tests\Fixtures\StringBackedEnumDummy;
use Symfony\Component\Serializer\Tests\Fixtures\UnitEnumDummy;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class BackedEnumNormalizerTest extends TestCase
{
    /**
     * @var BackedEnumNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new BackedEnumNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(StringBackedEnumDummy::GET));
        $this->assertTrue($this->normalizer->supportsNormalization(IntegerBackedEnumDummy::SUCCESS));
        $this->assertFalse($this->normalizer->supportsNormalization(UnitEnumDummy::GET));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $this->assertSame('GET', $this->normalizer->normalize(StringBackedEnumDummy::GET));
        $this->assertSame(200, $this->normalizer->normalize(IntegerBackedEnumDummy::SUCCESS));
    }

    public function testNormalizeBadObjectTypeThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(new \stdClass());
    }

    public function testSupportsDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization(null, StringBackedEnumDummy::class));
        $this->assertTrue($this->normalizer->supportsDenormalization(null, IntegerBackedEnumDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, UnitEnumDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    public function testDenormalize()
    {
        $this->assertSame(StringBackedEnumDummy::GET, $this->normalizer->denormalize('GET', StringBackedEnumDummy::class));
        $this->assertSame(IntegerBackedEnumDummy::SUCCESS, $this->normalizer->denormalize(200, IntegerBackedEnumDummy::class));
    }

    public function testDenormalizeNullValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(null, StringBackedEnumDummy::class);
    }

    public function testDenormalizeBooleanValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(true, StringBackedEnumDummy::class);
    }

    public function testDenormalizeObjectThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(new \stdClass(), StringBackedEnumDummy::class);
    }

    public function testDenormalizeBadBackingValueThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The data must belong to a backed enumeration of type '.StringBackedEnumDummy::class);

        $this->normalizer->denormalize('POST', StringBackedEnumDummy::class);
    }

    public function testNormalizeShouldThrowExceptionForNonEnumObjects()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The data must belong to a backed enumeration.');

        $this->normalizer->normalize(\stdClass::class);
    }

    public function testDenormalizeShouldThrowExceptionForNonEnumObjects()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The data must belong to a backed enumeration.');

        $this->normalizer->denormalize('GET', \stdClass::class);
    }

    public function testSupportsNormalizationShouldFailOnAnyPHPVersionForNonEnumObjects()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testItUsesTryFromIfContextIsPassed()
    {
        $this->assertNull($this->normalizer->denormalize(1, IntegerBackedEnumDummy::class, null, [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]));
        $this->assertNull($this->normalizer->denormalize('', IntegerBackedEnumDummy::class, null, [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]));
        $this->assertNull($this->normalizer->denormalize(null, IntegerBackedEnumDummy::class, null, [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]));

        $this->assertSame(IntegerBackedEnumDummy::SUCCESS, $this->normalizer->denormalize(200, IntegerBackedEnumDummy::class, null, [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]));

        $this->assertNull($this->normalizer->denormalize(1, StringBackedEnumDummy::class, null, [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]));
        $this->assertNull($this->normalizer->denormalize('foo', StringBackedEnumDummy::class, null, [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]));
        $this->assertNull($this->normalizer->denormalize(null, StringBackedEnumDummy::class, null, [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]));

        $this->assertSame(StringBackedEnumDummy::GET, $this->normalizer->denormalize('GET', StringBackedEnumDummy::class, null, [BackedEnumNormalizer::ALLOW_INVALID_VALUES => true]));
    }
}
