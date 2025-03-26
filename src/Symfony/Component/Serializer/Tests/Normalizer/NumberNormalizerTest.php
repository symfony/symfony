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

use BcMath\Number;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\NumberNormalizer;

class NumberNormalizerTest extends TestCase
{
    private NumberNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new NumberNormalizer();
    }

    /**
     * @dataProvider supportsNormalizationProvider
     */
    public function testSupportsNormalization(mixed $data, bool $expected)
    {
        $this->assertSame($expected, $this->normalizer->supportsNormalization($data));
    }

    public static function supportsNormalizationProvider(): iterable
    {
        if (class_exists(\GMP::class)) {
            yield 'GMP object' => [new \GMP('0b111'), true];
        }

        if (class_exists(Number::class)) {
            yield 'Number object' => [new Number('1.23'), true];
        }

        yield 'object with similar properties as Number' => [(object) ['value' => '1.23', 'scale' => 2], false];
        yield 'stdClass' => [new \stdClass(), false];
        yield 'string' => ['1.23', false];
        yield 'float' => [1.23, false];
        yield 'null' => [null, false];
    }

    /**
     * @requires extension bcmath
     *
     * @dataProvider normalizeGoodBcMathNumberValueProvider
     */
    public function testNormalizeBcMathNumber(Number $data, string $expected)
    {
        $this->assertSame($expected, $this->normalizer->normalize($data));
    }

    public static function normalizeGoodBcMathNumberValueProvider(): iterable
    {
        if (class_exists(Number::class)) {
            yield 'Number with scale=2' => [new Number('1.23'), '1.23'];
            yield 'Number with scale=0' => [new Number('1'), '1'];
            yield 'Number with integer' => [new Number(123), '123'];
        }
    }

    /**
     * @requires extension gmp
     *
     * @dataProvider normalizeGoodGmpValueProvider
     */
    public function testNormalizeGmp(\GMP $data, string $expected)
    {
        $this->assertSame($expected, $this->normalizer->normalize($data));
    }

    public static function normalizeGoodGmpValueProvider(): iterable
    {
        if (class_exists(\GMP::class)) {
            yield 'GMP hex' => [new \GMP('0x10'), '16'];
            yield 'GMP base=10' => [new \GMP('10'), '10'];
        }
    }

    /**
     * @dataProvider normalizeBadValueProvider
     */
    public function testNormalizeBadValueThrows(mixed $data)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The data must be an instance of "BcMath\Number" or "GMP".');

        $this->normalizer->normalize($data);
    }

    public static function normalizeBadValueProvider(): iterable
    {
        yield 'stdClass' => [new \stdClass()];
        yield 'string' => ['1.23'];
        yield 'null' => [null];
    }

    /**
     * @requires PHP 8.4
     * @requires extension bcmath
     */
    public function testSupportsBcMathNumberDenormalization()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization(null, Number::class));
    }

    /**
     * @requires extension gmp
     */
    public function testSupportsGmpDenormalization()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \GMP::class));
    }

    public function testDoesNotSupportOtherValuesDenormalization()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization(null, \stdClass::class));
    }

    /**
     * @requires PHP 8.4
     * @requires extension bcmath
     *
     * @dataProvider denormalizeGoodBcMathNumberValueProvider
     */
    public function testDenormalizeBcMathNumber(string|int $data, string $type, Number $expected)
    {
        $this->assertEquals($expected, $this->normalizer->denormalize($data, $type));
    }

    public static function denormalizeGoodBcMathNumberValueProvider(): iterable
    {
        if (class_exists(Number::class)) {
            yield 'Number, string with decimal point' => ['1.23', Number::class, new Number('1.23')];
            yield 'Number, integer as string' => ['123', Number::class, new Number('123')];
            yield 'Number, integer' => [123, Number::class, new Number('123')];
        }
    }

    /**
     * @dataProvider denormalizeGoodGmpValueProvider
     */
    public function testDenormalizeGmp(string|int $data, string $type, \GMP $expected)
    {
        $this->assertEquals($expected, $this->normalizer->denormalize($data, $type));
    }

    public static function denormalizeGoodGmpValueProvider(): iterable
    {
        if (class_exists(\GMP::class)) {
            yield 'GMP, large number' => ['9223372036854775808', \GMP::class, new \GMP('9223372036854775808')];
            yield 'GMP, integer' => [123, \GMP::class, new \GMP('123')];
        }
    }

    /**
     * @requires PHP 8.4
     * @requires extension bcmath
     *
     * @dataProvider denormalizeBadBcMathNumberValueProvider
     */
    public function testDenormalizeBadBcMathNumberValueThrows(mixed $data, string $type, string $expectedException, string $expectedExceptionMessage)
    {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->normalizer->denormalize($data, $type);
    }

    public static function denormalizeBadBcMathNumberValueProvider(): iterable
    {
        $stringOrDecimalExpectedMessage = 'The data must be a "string" representing a decimal number, or an "int".';
        yield 'Number, null' => [null, Number::class, NotNormalizableValueException::class, $stringOrDecimalExpectedMessage];
        yield 'Number, boolean' => [true, Number::class, NotNormalizableValueException::class, $stringOrDecimalExpectedMessage];
        yield 'Number, object' => [new \stdClass(), Number::class, NotNormalizableValueException::class, $stringOrDecimalExpectedMessage];
        yield 'Number, non-numeric string' => ['foobar', Number::class, NotNormalizableValueException::class, $stringOrDecimalExpectedMessage];
        yield 'Number, float' => [1.23, Number::class, NotNormalizableValueException::class, $stringOrDecimalExpectedMessage];
    }

    /**
     * @requires extension gmp
     *
     * @dataProvider denormalizeBadGmpValueProvider
     */
    public function testDenormalizeBadGmpValueThrows(mixed $data, string $type, string $expectedException, string $expectedExceptionMessage)
    {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->normalizer->denormalize($data, $type);
    }

    public static function denormalizeBadGmpValueProvider(): iterable
    {
        $stringOrIntExpectedMessage = 'The data must be a "string" representing an integer, or an "int".';
        yield 'GMP, null' => [null, \GMP::class, NotNormalizableValueException::class, $stringOrIntExpectedMessage];
        yield 'GMP, boolean' => [true, \GMP::class, NotNormalizableValueException::class, $stringOrIntExpectedMessage];
        yield 'GMP, object' => [new \stdClass(), \GMP::class, NotNormalizableValueException::class, $stringOrIntExpectedMessage];
        yield 'GMP, non-numeric string' => ['foobar', \GMP::class, NotNormalizableValueException::class, $stringOrIntExpectedMessage];
        yield 'GMP, scale > 0' => ['1.23', \GMP::class, NotNormalizableValueException::class, $stringOrIntExpectedMessage];
        yield 'GMP, float' => [1.23, \GMP::class, NotNormalizableValueException::class, $stringOrIntExpectedMessage];
    }

    public function testDenormalizeBadValueThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only "BcMath\Number" and "GMP" types are supported.');

        $this->normalizer->denormalize('1.23', \stdClass::class);
    }
}
