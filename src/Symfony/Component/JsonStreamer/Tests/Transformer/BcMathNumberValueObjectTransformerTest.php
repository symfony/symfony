<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Transformer;

use BcMath\Number;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Transformer\BcMathNumberValueObjectTransformer;

#[RequiresPhpExtension('bcmath')]
class BcMathNumberValueObjectTransformerTest extends TestCase
{
    public function testTransform()
    {
        $transformer = new BcMathNumberValueObjectTransformer();

        $this->assertSame('42', $transformer->transform(new Number(42)));
        $this->assertSame('3.14', $transformer->transform(new Number('3.14')));
        $this->assertSame('0', $transformer->transform(new Number(0)));
        $this->assertSame('-123', $transformer->transform(new Number(-123)));
    }

    public function testTransformThrowWhenInvalidNativeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The native value must be an instance of "BcMath\Number".');

        (new BcMathNumberValueObjectTransformer())->transform(new \stdClass());
    }

    public function testReverseTransform()
    {
        $transformer = new BcMathNumberValueObjectTransformer();

        $this->assertEquals(new Number(42), $transformer->reverseTransform(42));
        $this->assertEquals(new Number('3.14'), $transformer->reverseTransform('3.14'));
        $this->assertEquals(new Number('123'), $transformer->reverseTransform('123'));
        $this->assertEquals(new Number(0), $transformer->reverseTransform(0));
        $this->assertEquals(new Number('-123'), $transformer->reverseTransform('-123'));
    }

    public function testReverseTransformThrowWhenInvalidJsonValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value must be a string or an integer.');

        (new BcMathNumberValueObjectTransformer())->reverseTransform(3.14);
    }

    public function testReverseTransformThrowWhenInvalidNumberString()
    {
        $this->expectException(InvalidArgumentException::class);

        (new BcMathNumberValueObjectTransformer())->reverseTransform('not-a-number');
    }
}
