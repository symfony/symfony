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
use Symfony\Component\JsonStreamer\ValueTransformer\BcMathNumberToStringValueTransformer;

/**
 * @requires PHP 8.4
 * @requires extension bcmath
 */
class StringToBcMathNumberValueTransformerTest extends TestCase
{
    public function testTransform()
    {
        $this->assertSame('10', (new BcMathNumberToStringValueTransformer())->transform(new Number(10)));
    }

    public function testTransformNoOpInvalidStreamValueType()
    {
        $this->assertTrue(true, (new BcMathNumberToStringValueTransformer())->transform(true));
    }
}
