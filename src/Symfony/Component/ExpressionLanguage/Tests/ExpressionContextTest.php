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

use Symfony\Component\ExpressionLanguage\AbstractExpressionContext;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ExpressionContextTest extends \PHPUnit_Framework_TestCase
{
    public function testEvaluate()
    {
        $context = new ExpressionContextFixture1();

        $this->assertSame(2, $context->evaluate('1+1'));
        $this->assertSame('Hello bar', $context->evaluate("hello()~' '~foo"));
    }

    public function testParse()
    {
        $this->assertInstanceOf(
            'Symfony\Component\ExpressionLanguage\ParsedExpression',
            ExpressionContextFixture1::parse("hello()~' '~foo")
        );
    }

    public function testCompile()
    {
        $this->assertSame('(($helloer->sayHello() . " ") . $foo)', ExpressionContextFixture1::compile("hello()~' '~foo"));
    }
}

class ExpressionContextFixture1 extends AbstractExpressionContext
{
    protected function buildValues()
    {
        return array(
            'foo' => 'bar',
            'helloer' => new ExpressionContextFixture2(),
        );
    }

    public static function getNames()
    {
        return array('foo', 'helloer');
    }

    public static function registerFunctions(ExpressionLanguage $expressionLanguage)
    {
        $expressionLanguage->register(
            'hello',
            function () {
                return sprintf('$helloer->sayHello()');
            },
            function ($variables) {
                return $variables['helloer']->sayHello();
            }
        );
    }
}

class ExpressionContextFixture2
{
    public function sayHello()
    {
        return 'Hello';
    }
}
