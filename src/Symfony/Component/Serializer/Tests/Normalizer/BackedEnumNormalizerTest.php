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

    /**
     * @requires PHP 8.1
     */
    public function testSupportsNormalization()
    {
        self::assertTrue($this->normalizer->supportsNormalization(StringBackedEnumDummy::GET));
        self::assertTrue($this->normalizer->supportsNormalization(IntegerBackedEnumDummy::SUCCESS));
        self::assertFalse($this->normalizer->supportsNormalization(UnitEnumDummy::GET));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @requires PHP 8.1
     */
    public function testNormalize()
    {
        self::assertSame('GET', $this->normalizer->normalize(StringBackedEnumDummy::GET));
        self::assertSame(200, $this->normalizer->normalize(IntegerBackedEnumDummy::SUCCESS));
    }

    /**
     * @requires PHP 8.1
     */
    public function testNormalizeBadObjectTypeThrowsException()
    {
        self::expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(new \stdClass());
    }

    /**
     * @requires PHP 8.1
     */
    public function testSupportsDenormalization()
    {
        self::assertTrue($this->normalizer->supportsDenormalization(null, StringBackedEnumDummy::class));
        self::assertTrue($this->normalizer->supportsDenormalization(null, IntegerBackedEnumDummy::class));
        self::assertFalse($this->normalizer->supportsDenormalization(null, UnitEnumDummy::class));
        self::assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalize()
    {
        self::assertSame(StringBackedEnumDummy::GET, $this->normalizer->denormalize('GET', StringBackedEnumDummy::class));
        self::assertSame(IntegerBackedEnumDummy::SUCCESS, $this->normalizer->denormalize(200, IntegerBackedEnumDummy::class));
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalizeNullValueThrowsException()
    {
        self::expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(null, StringBackedEnumDummy::class);
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalizeBooleanValueThrowsException()
    {
        self::expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(true, StringBackedEnumDummy::class);
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalizeObjectThrowsException()
    {
        self::expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(new \stdClass(), StringBackedEnumDummy::class);
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalizeBadBackingValueThrowsException()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The data must belong to a backed enumeration of type '.StringBackedEnumDummy::class);

        $this->normalizer->denormalize('POST', StringBackedEnumDummy::class);
    }

    public function testNormalizeShouldThrowExceptionForNonEnumObjects()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The data must belong to a backed enumeration.');

        $this->normalizer->normalize(\stdClass::class);
    }

    public function testDenormalizeShouldThrowExceptionForNonEnumObjects()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The data must belong to a backed enumeration.');

        $this->normalizer->denormalize('GET', \stdClass::class);
    }

    public function testSupportsNormalizationShouldFailOnAnyPHPVersionForNonEnumObjects()
    {
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }
}
