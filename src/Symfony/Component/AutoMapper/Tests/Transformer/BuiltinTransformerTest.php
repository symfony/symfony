<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Transformer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\Transformer\BuiltinTransformer;
use Symfony\Component\PropertyInfo\Type;

class BuiltinTransformerTest extends TestCase
{
    use EvalTransformerTrait;

    public function testStringToString()
    {
        $transformer = new BuiltinTransformer(new Type('string'), [new Type('string')]);
        $output = $this->evalTransformer($transformer, 'foo');

        self::assertSame('foo', $output);
    }

    public function testStringToArray()
    {
        $transformer = new BuiltinTransformer(new Type('string'), [new Type('array')]);
        $output = $this->evalTransformer($transformer, 'foo');

        self::assertSame(['foo'], $output);
    }

    public function testStringToIterable()
    {
        $transformer = new BuiltinTransformer(new Type('string'), [new Type('iterable')]);
        $output = $this->evalTransformer($transformer, 'foo');

        self::assertSame(['foo'], $output);
    }

    public function testStringToFloat()
    {
        $transformer = new BuiltinTransformer(new Type('string'), [new Type('float')]);
        $output = $this->evalTransformer($transformer, '12.2');

        self::assertSame(12.2, $output);
    }

    public function testStringToInt()
    {
        $transformer = new BuiltinTransformer(new Type('string'), [new Type('int')]);
        $output = $this->evalTransformer($transformer, '12');

        self::assertSame(12, $output);
    }

    public function testStringToBool()
    {
        $transformer = new BuiltinTransformer(new Type('string'), [new Type('bool')]);
        $output = $this->evalTransformer($transformer, 'foo');

        self::assertSame(true, $output);

        $output = $this->evalTransformer($transformer, '');

        self::assertSame(false, $output);
    }

    public function testBoolToInt()
    {
        $transformer = new BuiltinTransformer(new Type('bool'), [new Type('int')]);
        $output = $this->evalTransformer($transformer, true);

        self::assertSame(1, $output);

        $output = $this->evalTransformer($transformer, false);

        self::assertSame(0, $output);
    }

    public function testBoolToString()
    {
        $transformer = new BuiltinTransformer(new Type('bool'), [new Type('string')]);

        $output = $this->evalTransformer($transformer, true);

        self::assertSame('1', $output);

        $output = $this->evalTransformer($transformer, false);

        self::assertSame('', $output);
    }

    public function testBoolToFloat()
    {
        $transformer = new BuiltinTransformer(new Type('bool'), [new Type('float')]);

        $output = $this->evalTransformer($transformer, true);

        self::assertSame(1.0, $output);

        $output = $this->evalTransformer($transformer, false);

        self::assertSame(0.0, $output);
    }

    public function testBoolToArray()
    {
        $transformer = new BuiltinTransformer(new Type('bool'), [new Type('array')]);

        $output = $this->evalTransformer($transformer, true);

        self::assertSame([true], $output);

        $output = $this->evalTransformer($transformer, false);

        self::assertSame([false], $output);
    }

    public function testBoolToIterable()
    {
        $transformer = new BuiltinTransformer(new Type('bool'), [new Type('iterable')]);

        $output = $this->evalTransformer($transformer, true);

        self::assertSame([true], $output);

        $output = $this->evalTransformer($transformer, false);

        self::assertSame([false], $output);
    }

    public function testBoolToBool()
    {
        $transformer = new BuiltinTransformer(new Type('bool'), [new Type('bool')]);

        $output = $this->evalTransformer($transformer, true);

        self::assertSame(true, $output);

        $output = $this->evalTransformer($transformer, false);

        self::assertSame(false, $output);
    }

    public function testFloatToString()
    {
        $transformer = new BuiltinTransformer(new Type('float'), [new Type('string')]);

        $output = $this->evalTransformer($transformer, 12.23);

        self::assertSame('12.23', $output);
    }

    public function testFloatToInt()
    {
        $transformer = new BuiltinTransformer(new Type('float'), [new Type('int')]);

        $output = $this->evalTransformer($transformer, 12.23);

        self::assertSame(12, $output);
    }

    public function testFloatToBool()
    {
        $transformer = new BuiltinTransformer(new Type('float'), [new Type('bool')]);

        $output = $this->evalTransformer($transformer, 12.23);

        self::assertSame(true, $output);

        $output = $this->evalTransformer($transformer, 0.0);

        self::assertSame(false, $output);
    }

    public function testFloatToArray()
    {
        $transformer = new BuiltinTransformer(new Type('float'), [new Type('array')]);

        $output = $this->evalTransformer($transformer, 12.23);

        self::assertSame([12.23], $output);
    }

    public function testFloatToIterable()
    {
        $transformer = new BuiltinTransformer(new Type('float'), [new Type('iterable')]);

        $output = $this->evalTransformer($transformer, 12.23);

        self::assertSame([12.23], $output);
    }

    public function testFloatToFloat()
    {
        $transformer = new BuiltinTransformer(new Type('float'), [new Type('float')]);

        $output = $this->evalTransformer($transformer, 12.23);

        self::assertSame(12.23, $output);
    }

    public function testIntToInt()
    {
        $transformer = new BuiltinTransformer(new Type('int'), [new Type('int')]);

        $output = $this->evalTransformer($transformer, 12);

        self::assertSame(12, $output);
    }

    public function testIntToFloat()
    {
        $transformer = new BuiltinTransformer(new Type('int'), [new Type('float')]);

        $output = $this->evalTransformer($transformer, 12);

        self::assertSame(12.0, $output);
    }

    public function testIntToString()
    {
        $transformer = new BuiltinTransformer(new Type('int'), [new Type('string')]);

        $output = $this->evalTransformer($transformer, 12);

        self::assertSame('12', $output);
    }

    public function testIntToBool()
    {
        $transformer = new BuiltinTransformer(new Type('int'), [new Type('bool')]);

        $output = $this->evalTransformer($transformer, 12);

        self::assertSame(true, $output);

        $output = $this->evalTransformer($transformer, 0);

        self::assertSame(false, $output);
    }

    public function testIntToArray()
    {
        $transformer = new BuiltinTransformer(new Type('int'), [new Type('array')]);

        $output = $this->evalTransformer($transformer, 12);

        self::assertSame([12], $output);
    }

    public function testIntToIterable()
    {
        $transformer = new BuiltinTransformer(new Type('int'), [new Type('iterable')]);

        $output = $this->evalTransformer($transformer, 12);

        self::assertSame([12], $output);
    }

    public function testIterableToArray()
    {
        $transformer = new BuiltinTransformer(new Type('iterable'), [new Type('array')]);

        $closure = function () {
            yield 1;
            yield 2;
        };

        $output = $this->evalTransformer($transformer, $closure());

        self::assertSame([1, 2], $output);
    }

    public function testArrayToIterable()
    {
        $transformer = new BuiltinTransformer(new Type('array'), [new Type('iterable')]);
        $output = $this->evalTransformer($transformer, [1, 2]);

        self::assertSame([1, 2], $output);
    }

    public function testToUnknowCast()
    {
        $transformer = new BuiltinTransformer(new Type('callable'), [new Type('string')]);

        $output = $this->evalTransformer($transformer, function ($test) {
            return $test;
        });

        self::assertIsCallable($output);
    }
}
