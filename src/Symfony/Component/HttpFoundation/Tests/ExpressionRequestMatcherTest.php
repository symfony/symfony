<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\ExpressionRequestMatcher;
use Symfony\Component\HttpFoundation\Request;

class ExpressionRequestMatcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \LogicException
     */
    public function testNoLanguageSet()
    {
        $expressionRequestMatcher = new ExpressionRequestMatcher();
        $expressionRequestMatcher->matches(new Request());
    }

    public function testMatchesWithParentMatchesTrue()
    {
        $expressionRequestMatcher = new ExpressionRequestMatcher();
        $expressionRequestMatcher->setExpression(new ExpressionLanguage(), 'request.getMethod() == method');

        $request = Request::create('/foo');
        $this->assertTrue($expressionRequestMatcher->matches($request));

        $expressionRequestMatcher->setExpression(new ExpressionLanguage(), 'request.getMethod() != method');
        $this->assertFalse($expressionRequestMatcher->matches($request));
    }

    public function testMatchesWithParentMatchesFalse()
    {
        $expressionRequestMatcher = new ExpressionRequestMatcher();
        $expressionRequestMatcher->setExpression(new ExpressionLanguage(), 'request.getMethod() == method');
        $expressionRequestMatcher->matchAttribute('foo', 'bar');

        $request = Request::create('/foo');
        $request->attributes->set('foo', 'foo');
        $this->assertFalse($expressionRequestMatcher->matches($request));
    }
}
