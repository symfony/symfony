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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\ExpressionLanguage\Tests\Fixtures\TestProvider;

class ExpressionLanguageTest extends TestCase
{
    public function testCachedParse()
    {
        $cacheMock = self::createMock(CacheItemPoolInterface::class);
        $cacheItemMock = self::createMock(CacheItemInterface::class);
        $savedParsedExpression = null;
        $expressionLanguage = new ExpressionLanguage($cacheMock);

        $cacheMock
            ->expects(self::exactly(2))
            ->method('getItem')
            ->with('1%20%2B%201%2F%2F')
            ->willReturn($cacheItemMock)
        ;

        $cacheItemMock
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function () use (&$savedParsedExpression) {
                return $savedParsedExpression;
            })
        ;

        $cacheItemMock
            ->expects(self::exactly(1))
            ->method('set')
            ->with(self::isInstanceOf(ParsedExpression::class))
            ->willReturnCallback(function ($parsedExpression) use (&$savedParsedExpression, $cacheItemMock) {
                $savedParsedExpression = $parsedExpression;

                return $cacheItemMock;
            })
        ;

        $cacheMock
            ->expects(self::exactly(1))
            ->method('save')
            ->with($cacheItemMock)
            ->willReturn(true)
        ;

        $parsedExpression = $expressionLanguage->parse('1 + 1', []);
        self::assertSame($savedParsedExpression, $parsedExpression);

        $parsedExpression = $expressionLanguage->parse('1 + 1', []);
        self::assertSame($savedParsedExpression, $parsedExpression);
    }

    public function testConstantFunction()
    {
        $expressionLanguage = new ExpressionLanguage();
        self::assertEquals(\PHP_VERSION, $expressionLanguage->evaluate('constant("PHP_VERSION")'));

        $expressionLanguage = new ExpressionLanguage();
        self::assertEquals('\constant("PHP_VERSION")', $expressionLanguage->compile('constant("PHP_VERSION")'));
    }

    public function testProviders()
    {
        $expressionLanguage = new ExpressionLanguage(null, [new TestProvider()]);
        self::assertEquals('foo', $expressionLanguage->evaluate('identity("foo")'));
        self::assertEquals('"foo"', $expressionLanguage->compile('identity("foo")'));
        self::assertEquals('FOO', $expressionLanguage->evaluate('strtoupper("foo")'));
        self::assertEquals('\strtoupper("foo")', $expressionLanguage->compile('strtoupper("foo")'));
        self::assertEquals('foo', $expressionLanguage->evaluate('strtolower("FOO")'));
        self::assertEquals('\strtolower("FOO")', $expressionLanguage->compile('strtolower("FOO")'));
        self::assertTrue($expressionLanguage->evaluate('fn_namespaced()'));
        self::assertEquals('\Symfony\Component\ExpressionLanguage\Tests\Fixtures\fn_namespaced()', $expressionLanguage->compile('fn_namespaced()'));
    }

    /**
     * @dataProvider shortCircuitProviderEvaluate
     */
    public function testShortCircuitOperatorsEvaluate($expression, array $values, $expected)
    {
        $expressionLanguage = new ExpressionLanguage();
        self::assertEquals($expected, $expressionLanguage->evaluate($expression, $values));
    }

    /**
     * @dataProvider shortCircuitProviderCompile
     */
    public function testShortCircuitOperatorsCompile($expression, array $names, $expected)
    {
        $result = null;
        $expressionLanguage = new ExpressionLanguage();
        eval(sprintf('$result = %s;', $expressionLanguage->compile($expression, $names)));
        self::assertSame($expected, $result);
    }

    public function testParseThrowsInsteadOfNotice()
    {
        self::expectException(SyntaxError::class);
        self::expectExceptionMessage('Unexpected end of expression around position 6 for expression `node.`.');
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->parse('node.', ['node']);
    }

    public function shortCircuitProviderEvaluate()
    {
        $object = self::getMockBuilder(\stdClass::class)->setMethods(['foo'])->getMock();
        $object->expects(self::never())->method('foo');

        return [
            ['false and object.foo()', ['object' => $object], false],
            ['false && object.foo()', ['object' => $object], false],
            ['true || object.foo()', ['object' => $object], true],
            ['true or object.foo()', ['object' => $object], true],
        ];
    }

    public function shortCircuitProviderCompile()
    {
        return [
            ['false and foo', ['foo' => 'foo'], false],
            ['false && foo', ['foo' => 'foo'], false],
            ['true || foo', ['foo' => 'foo'], true],
            ['true or foo', ['foo' => 'foo'], true],
        ];
    }

    public function testCachingForOverriddenVariableNames()
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = 'a + b';
        $expressionLanguage->evaluate($expression, ['a' => 1, 'b' => 1]);
        $result = $expressionLanguage->compile($expression, ['a', 'B' => 'b']);
        self::assertSame('($a + $B)', $result);
    }

    public function testStrictEquality()
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = '123 === a';
        $result = $expressionLanguage->compile($expression, ['a']);
        self::assertSame('(123 === $a)', $result);
    }

    public function testCachingWithDifferentNamesOrder()
    {
        $cacheMock = self::createMock(CacheItemPoolInterface::class);
        $cacheItemMock = self::createMock(CacheItemInterface::class);
        $expressionLanguage = new ExpressionLanguage($cacheMock);
        $savedParsedExpression = null;

        $cacheMock
            ->expects(self::exactly(2))
            ->method('getItem')
            ->with('a%20%2B%20b%2F%2Fa%7CB%3Ab')
            ->willReturn($cacheItemMock)
        ;

        $cacheItemMock
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function () use (&$savedParsedExpression) {
                return $savedParsedExpression;
            })
        ;

        $cacheItemMock
            ->expects(self::exactly(1))
            ->method('set')
            ->with(self::isInstanceOf(ParsedExpression::class))
            ->willReturnCallback(function ($parsedExpression) use (&$savedParsedExpression, $cacheItemMock) {
                $savedParsedExpression = $parsedExpression;

                return $cacheItemMock;
            })
        ;

        $cacheMock
            ->expects(self::exactly(1))
            ->method('save')
            ->with($cacheItemMock)
            ->willReturn(true)
        ;

        $expression = 'a + b';
        $expressionLanguage->compile($expression, ['a', 'B' => 'b']);
        $expressionLanguage->compile($expression, ['B' => 'b', 'a']);
    }

    public function testOperatorCollisions()
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = 'foo.not in [bar]';
        $compiled = $expressionLanguage->compile($expression, ['foo', 'bar']);
        self::assertSame('in_array($foo->not, [0 => $bar])', $compiled);

        $result = $expressionLanguage->evaluate($expression, ['foo' => (object) ['not' => 'test'], 'bar' => 'test']);
        self::assertTrue($result);
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterParse($registerCallback)
    {
        self::expectException(\LogicException::class);
        $el = new ExpressionLanguage();
        $el->parse('1 + 1', []);
        $registerCallback($el);
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterEval($registerCallback)
    {
        self::expectException(\LogicException::class);
        $el = new ExpressionLanguage();
        $el->evaluate('1 + 1');
        $registerCallback($el);
    }

    public function testCallBadCallable()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessageMatches('/Unable to call method "\w+" of object "\w+"./');
        $el = new ExpressionLanguage();
        $el->evaluate('foo.myfunction()', ['foo' => new \stdClass()]);
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterCompile($registerCallback)
    {
        self::expectException(\LogicException::class);
        $el = new ExpressionLanguage();
        $el->compile('1 + 1');
        $registerCallback($el);
    }

    public function getRegisterCallbacks()
    {
        return [
            [
                function (ExpressionLanguage $el) {
                    $el->register('fn', function () {}, function () {});
                },
            ],
            [
                function (ExpressionLanguage $el) {
                    $el->addFunction(new ExpressionFunction('fn', function () {}, function () {}));
                },
            ],
            [
                function (ExpressionLanguage $el) {
                    $el->registerProvider(new TestProvider());
                },
            ],
        ];
    }
}
