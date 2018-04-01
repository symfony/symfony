<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\ExpressionLanguage\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\ExpressionLanguage\Expression;

class ExpressionTest extends TestCase
{
    public function testSerialization()
    {
        $expression = new Expression('kernel.boot()');

        $serializedExpression = serialize($expression);
        $unserializedExpression = unserialize($serializedExpression);

        $this->assertEquals($expression, $unserializedExpression);
    }
}
