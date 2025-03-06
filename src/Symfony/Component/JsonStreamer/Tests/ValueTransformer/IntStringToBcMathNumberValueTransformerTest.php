<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\ValueTransformer;

use BcMath\Number;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\ValueTransformer\IntStringToBcMathNumberValueTransformer;

/**
 * @requires PHP 8.4
 * @requires extension bcmath
 */
class IntStringToBcMathNumberValueTransformerTest extends TestCase
{
    public function testTransform()
    {
        $this->assertEquals(new Number(10), (new IntStringToBcMathNumberValueTransformer())->transform('10'));
    }

    public function testTransformNoOpWhenInvalidNativeValueType()
    {
        $this->assertTrue((new IntStringToBcMathNumberValueTransformer())->transform(true));
    }

    public function testTransformThrowWhenInvalidStreamValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Unable to create a "%s"; the stream value must a parsable string or an int.', Number::class));

        (new IntStringToBcMathNumberValueTransformer())->transform('not valid');
    }
}
