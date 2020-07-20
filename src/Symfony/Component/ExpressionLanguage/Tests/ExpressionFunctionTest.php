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

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

/**
 * Tests ExpressionFunction.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class ExpressionFunctionTest extends TestCase
{
    public function testFunctionDoesNotExist()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('PHP function "fn_does_not_exist" does not exist.');
        ExpressionFunction::fromPhp('fn_does_not_exist');
    }

    public function testFunctionNamespaced()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('An expression function name must be defined when PHP function "Symfony\Component\ExpressionLanguage\Tests\fn_namespaced" is namespaced.');
        ExpressionFunction::fromPhp('Symfony\Component\ExpressionLanguage\Tests\fn_namespaced');
    }
}

function fn_namespaced()
{
}
