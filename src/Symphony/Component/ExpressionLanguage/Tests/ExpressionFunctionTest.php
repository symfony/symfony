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
use Symphony\Component\ExpressionLanguage\ExpressionFunction;

/**
 * Tests ExpressionFunction.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class ExpressionFunctionTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage PHP function "fn_does_not_exist" does not exist.
     */
    public function testFunctionDoesNotExist()
    {
        ExpressionFunction::fromPhp('fn_does_not_exist');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage An expression function name must be defined when PHP function "Symphony\Component\ExpressionLanguage\Tests\fn_namespaced" is namespaced.
     */
    public function testFunctionNamespaced()
    {
        ExpressionFunction::fromPhp('Symphony\Component\ExpressionLanguage\Tests\fn_namespaced');
    }
}

function fn_namespaced()
{
}
