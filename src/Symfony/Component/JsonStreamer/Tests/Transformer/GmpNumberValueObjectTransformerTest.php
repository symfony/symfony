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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Transformer\GmpNumberValueObjectTransformer;

#[RequiresPhpExtension('gmp')]
class GmpNumberValueObjectTransformerTest extends TestCase
{
    public function testTransform()
    {
        $transformer = new GmpNumberValueObjectTransformer();

        $this->assertSame('42', $transformer->transform(new \GMP(42)));
        $this->assertSame('16', $transformer->transform(new \GMP('0x10')));
        $this->assertSame('0', $transformer->transform(new \GMP(0)));
        $this->assertSame('-123', $transformer->transform(new \GMP(-123)));
        $this->assertSame('99999999999999999999', $transformer->transform(new \GMP('99999999999999999999')));
    }

    public function testTransformThrowWhenInvalidNativeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The native value must be an instance of "\GMP".');

        (new GmpNumberValueObjectTransformer())->transform(new \stdClass());
    }

    public function testReverseTransform()
    {
        $transformer = new GmpNumberValueObjectTransformer();

        $this->assertEquals(new \GMP(42), $transformer->reverseTransform(42));
        $this->assertEquals(new \GMP('123'), $transformer->reverseTransform('123'));
        $this->assertEquals(new \GMP('9223372036854775808'), $transformer->reverseTransform('9223372036854775808'));
        $this->assertEquals(new \GMP(0), $transformer->reverseTransform(0));
        $this->assertEquals(new \GMP('-123'), $transformer->reverseTransform('-123'));
    }

    public function testReverseTransformThrowWhenInvalidJsonValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value must be a string or an integer.');

        (new GmpNumberValueObjectTransformer())->reverseTransform(3.14);
    }

    public function testReverseTransformThrowWhenInvalidGmpString()
    {
        $this->expectException(InvalidArgumentException::class);

        (new GmpNumberValueObjectTransformer())->reverseTransform('not-a-number');
    }
}
