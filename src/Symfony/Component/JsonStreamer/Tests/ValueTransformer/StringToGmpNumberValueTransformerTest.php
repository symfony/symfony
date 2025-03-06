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

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\ValueTransformer\GmpNumberToStringValueTransformer;

/**
 * @requires extension gmp
 */
class StringToGmpNumberValueTransformerTest extends TestCase
{
    public function testTransform()
    {
        $this->assertSame('10', (new GmpNumberToStringValueTransformer())->transform(new \GMP(10)));
    }

    public function testTransformNoOpInvalidStreamValueType()
    {
        $this->assertTrue(true, (new GmpNumberToStringValueTransformer())->transform(true));
    }
}
