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
        $this->assertTrue($this->normalizer->supportsNormalization(StringBackedEnumDummy::GET));
        $this->assertTrue($this->normalizer->supportsNormalization(IntegerBackedEnumDummy::SUCCESS));
        $this->assertFalse($this->normalizer->supportsNormalization(UnitEnumDummy::GET));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    /**
     * @requires PHP 8.1
     */
    public function testNormalize()
    {
        $this->assertSame('GET', $this->normalizer->normalize(StringBackedEnumDummy::GET));
        $this->assertSame(200, $this->normalizer->normalize(IntegerBackedEnumDummy::SUCCESS));
    }

    /**
     * @requires PHP 8.1
     */
    public function testNormalizeBadObjectTypeThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->normalize(new \stdClass());
    }

    /**
     * @requires PHP 8.1
     */
    public function testSupportsDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization(null, StringBackedEnumDummy::class));
        $this->assertTrue($this->normalizer->supportsDenormalization(null, IntegerBackedEnumDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, UnitEnumDummy::class));
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalize()
    {
        $this->assertSame(StringBackedEnumDummy::GET, $this->normalizer->denormalize('GET', StringBackedEnumDummy::class));
        $this->assertSame(IntegerBackedEnumDummy::SUCCESS, $this->normalizer->denormalize(200, IntegerBackedEnumDummy::class));
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalizeNullValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(null, StringBackedEnumDummy::class);
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalizeBooleanValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(true, StringBackedEnumDummy::class);
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalizeObjectThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(new \stdClass(), StringBackedEnumDummy::class);
    }

    /**
     * @requires PHP 8.1
     */
    public function testDenormalizeBadBackingValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('"POST" is not a valid backing value for enum "'.StringBackedEnumDummy::class.'"');
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
}
