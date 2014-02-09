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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ExpressionLanguageTest extends \PHPUnit_Framework_TestCase
{
    public function testCachedParse()
    {
        $cacheMock = $this->getMock('Symfony\Component\ExpressionLanguage\ParserCache\ParserCacheInterface');
        $savedParsedExpression = null;
        $expressionLanguage = new ExpressionLanguage($cacheMock);

        $cacheMock
            ->expects($this->exactly(2))
            ->method('fetch')
            ->with('1 + 1//')
            ->will($this->returnCallback(function () use (&$savedParsedExpression) {
                return $savedParsedExpression;
            }))
        ;
        $cacheMock
            ->expects($this->exactly(1))
            ->method('save')
            ->with('1 + 1//', $this->isInstanceOf('Symfony\Component\ExpressionLanguage\ParsedExpression'))
            ->will($this->returnCallback(function ($key, $expression) use (&$savedParsedExpression) {
                $savedParsedExpression = $expression;
            }))
        ;

        $parsedExpression = $expressionLanguage->parse('1 + 1', array());
        $this->assertSame($savedParsedExpression, $parsedExpression);

        $parsedExpression = $expressionLanguage->parse('1 + 1', array());
        $this->assertSame($savedParsedExpression, $parsedExpression);
    }

    public function testRegisterFunctions()
    {
        $expressionLanguage = new ExpressionLanguage();
        $functions = $this->getFunctions($expressionLanguage);

        $this->assertArrayHasKey('constant', $functions);
        $this->assertArrayHasKey('compiler', $functions['constant']);
        $this->assertArrayHasKey('evaluator', $functions['constant']);

        $this->assertTrue(is_callable($functions['constant']['compiler']));
        $this->assertTrue(is_callable($functions['constant']['evaluator']));

        $this->assertEquals($functions['constant']['compiler']('expression_language_foo'), 'constant(expression_language_foo)');
        define('expression_language_foo', 'foo');
        $this->assertEquals($functions['constant']['evaluator']('expression_language_foo'), 'foo');
    }

    private function getFunctions($obj)
    {
        $class = new \ReflectionClass('Symfony\Component\ExpressionLanguage\ExpressionLanguage');
        $property = $class->getProperty('functions');
        $property->setAccessible(true);

        return $property->getValue($obj);
    }
}
