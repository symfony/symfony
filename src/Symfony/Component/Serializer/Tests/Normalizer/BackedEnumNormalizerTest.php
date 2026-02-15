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

use PHPUnit\Framework\Attributes\TestWith;
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
    private BackedEnumNormalizer $normalizer;

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

    #[TestWith([StringBackedEnumDummy::GET, 'GET', StringBackedEnumDummy::class], 'string backed enum')]
    #[TestWith([IntegerBackedEnumDummy::SUCCESS, 200, IntegerBackedEnumDummy::class], 'int backed enum')]
    #[TestWith([IntegerBackedEnumDummy::SUCCESS, '200', IntegerBackedEnumDummy::class], 'int backed enum with string value')]
    public function testDenormalize(mixed $expected, mixed $data, string $type)
    {
        $this->assertSame($expected, $this->normalizer->denormalize($data, $type));
    }

    public function testDenormalizeNullValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data is neither an integer nor a string, you should pass an integer or a string');

        $this->normalizer->denormalize(null, StringBackedEnumDummy::class);
    }

    public function testDenormalizeBooleanValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data is neither an integer nor a string, you should pass an integer or a string');

        $this->normalizer->denormalize(true, StringBackedEnumDummy::class);
    }

    public function testDenormalizeObjectThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data is neither an integer nor a string, you should pass an integer or a string');

        $this->normalizer->denormalize(new \stdClass(), StringBackedEnumDummy::class);
    }

    public function testDenormalizeInvalidBackedTypeThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data must be of type string');

        $this->normalizer->denormalize(8, StringBackedEnumDummy::class);
    }

    public function testDenormalizeInvalidIntegerBackedValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data must be one of the following values: 200, 404');

        $this->normalizer->denormalize(300, IntegerBackedEnumDummy::class);
    }

    public function testDenormalizeInvalidStringBackedValueThrowsException()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage("The data must be one of the following values: 'GET', 'OPTIONS'");

        $this->normalizer->denormalize('POST', StringBackedEnumDummy::class);
    }

    public function testDenormalizeInvalidBackedValueWithAllowInvalidAndCollectErrorsThrows()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage("The data must be one of the following values: 'GET', 'OPTIONS'");

        $context = [
            BackedEnumNormalizer::ALLOW_INVALID_VALUES => true,
            'not_normalizable_value_exceptions' => [],
        ];

        $this->normalizer->denormalize('invalid-value', StringBackedEnumDummy::class, null, $context);
    }

    public function testDenormalizeNullWithAllowInvalidAndCollectErrorsThrows()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The data is neither an integer nor a string');

        $context = [
            BackedEnumNormalizer::ALLOW_INVALID_VALUES => true,
            'not_normalizable_value_exceptions' => [], // Indicate that we want to collect errors
        ];

        $this->normalizer->denormalize(null, StringBackedEnumDummy::class, null, $context);
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
