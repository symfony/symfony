<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests;

use Symfony\Component\ExpressionLanguage\ExpressionPhpFunction;

/**
 * Tests ExpressionPhpFunction.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class ExpressionPhpFunctionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage PHP function "fn_does_not_exist" does not exist.
     */
    public function testFunctionDoesNotExist()
    {
        new ExpressionPhpFunction('fn_does_not_exist');
    }
}
