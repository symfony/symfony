<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\ExpressionLanguage\ExpressionLanguage;
use Symphony\Component\HttpFoundation\ExpressionRequestMatcher;
use Symphony\Component\HttpFoundation\Request;

class ExpressionRequestMatcherTest extends TestCase
{
    /**
     * @expectedException \LogicException
     */
    public function testWhenNoExpressionIsSet()
    {
        $expressionRequestMatcher = new ExpressionRequestMatcher();
        $expressionRequestMatcher->matches(new Request());
    }

    /**
     * @dataProvider provideExpressions
     */
    public function testMatchesWhenParentMatchesIsTrue($expression, $expected)
    {
        $request = Request::create('/foo');
        $expressionRequestMatcher = new ExpressionRequestMatcher();

        $expressionRequestMatcher->setExpression(new ExpressionLanguage(), $expression);
        $this->assertSame($expected, $expressionRequestMatcher->matches($request));
    }

    /**
     * @dataProvider provideExpressions
     */
    public function testMatchesWhenParentMatchesIsFalse($expression)
    {
        $request = Request::create('/foo');
        $request->attributes->set('foo', 'foo');
        $expressionRequestMatcher = new ExpressionRequestMatcher();
        $expressionRequestMatcher->matchAttribute('foo', 'bar');

        $expressionRequestMatcher->setExpression(new ExpressionLanguage(), $expression);
        $this->assertFalse($expressionRequestMatcher->matches($request));
    }

    public function provideExpressions()
    {
        return array(
            array('request.getMethod() == method', true),
            array('request.getPathInfo() == path', true),
            array('request.getHost() == host', true),
            array('request.getClientIp() == ip', true),
            array('request.attributes.all() == attributes', true),
            array('request.getMethod() == method && request.getPathInfo() == path && request.getHost() == host && request.getClientIp() == ip &&  request.attributes.all() == attributes', true),
            array('request.getMethod() != method', false),
            array('request.getMethod() != method && request.getPathInfo() == path && request.getHost() == host && request.getClientIp() == ip &&  request.attributes.all() == attributes', false),
        );
    }
}
