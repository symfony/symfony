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
        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $savedParsedExpression = null;
        $expressionLanguage = new ExpressionLanguage($cacheMock);

        $cacheMock
            ->expects($this->exactly(2))
            ->method('getItem')
            ->with('1%20%2B%201%2F%2F')
            ->willReturn($cacheItemMock)
        ;

        $cacheItemMock
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function () use (&$savedParsedExpression) {
                return $savedParsedExpression;
            })
        ;

        $cacheItemMock
            ->expects($this->exactly(1))
            ->method('set')
            ->with($this->isInstanceOf(ParsedExpression::class))
            ->willReturnCallback(function ($parsedExpression) use (&$savedParsedExpression, $cacheItemMock) {
                $savedParsedExpression = $parsedExpression;

                return $cacheItemMock;
            })
        ;

        $cacheMock
            ->expects($this->exactly(1))
            ->method('save')
            ->with($cacheItemMock)
            ->willReturn(true)
        ;

        $parsedExpression = $expressionLanguage->parse('1 + 1', []);
        $this->assertSame($savedParsedExpression, $parsedExpression);

        $parsedExpression = $expressionLanguage->parse('1 + 1', []);
        $this->assertSame($savedParsedExpression, $parsedExpression);
    }

    public function testConstantFunction()
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals(\PHP_VERSION, $expressionLanguage->evaluate('constant("PHP_VERSION")'));

        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals('\constant("PHP_VERSION")', $expressionLanguage->compile('constant("PHP_VERSION")'));
    }

    public function testProviders()
    {
        $expressionLanguage = new ExpressionLanguage(null, [new TestProvider()]);
        $this->assertEquals('foo', $expressionLanguage->evaluate('identity("foo")'));
        $this->assertEquals('"foo"', $expressionLanguage->compile('identity("foo")'));
        $this->assertEquals('FOO', $expressionLanguage->evaluate('strtoupper("foo")'));
        $this->assertEquals('\strtoupper("foo")', $expressionLanguage->compile('strtoupper("foo")'));
        $this->assertEquals('foo', $expressionLanguage->evaluate('strtolower("FOO")'));
        $this->assertEquals('\strtolower("FOO")', $expressionLanguage->compile('strtolower("FOO")'));
        $this->assertTrue($expressionLanguage->evaluate('fn_namespaced()'));
        $this->assertEquals('\Symfony\Component\ExpressionLanguage\Tests\Fixtures\fn_namespaced()', $expressionLanguage->compile('fn_namespaced()'));
    }

    /**
     * @dataProvider shortCircuitProviderEvaluate
     */
    public function testShortCircuitOperatorsEvaluate($expression, array $values, $expected)
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertEquals($expected, $expressionLanguage->evaluate($expression, $values));
    }

    /**
     * @dataProvider shortCircuitProviderCompile
     */
    public function testShortCircuitOperatorsCompile($expression, array $names, $expected)
    {
        $result = null;
        $expressionLanguage = new ExpressionLanguage();
        eval(sprintf('$result = %s;', $expressionLanguage->compile($expression, $names)));
        $this->assertSame($expected, $result);
    }

    public function testParseThrowsInsteadOfNotice()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unexpected end of expression around position 6 for expression `node.`.');
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->parse('node.', ['node']);
    }

    public function shortCircuitProviderEvaluate()
    {
        $object = new class(\Closure::fromCallable([static::class, 'fail'])) {
            private $fail;

            public function __construct(callable $fail)
            {
                $this->fail = $fail;
            }

            public function foo()
            {
                ($this->fail)();
            }
        };

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
        $this->assertSame('($a + $B)', $result);
    }

    public function testStrictEquality()
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = '123 === a';
        $result = $expressionLanguage->compile($expression, ['a']);
        $this->assertSame('(123 === $a)', $result);
    }

    public function testCachingWithDifferentNamesOrder()
    {
        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $expressionLanguage = new ExpressionLanguage($cacheMock);
        $savedParsedExpression = null;

        $cacheMock
            ->expects($this->exactly(2))
            ->method('getItem')
            ->with('a%20%2B%20b%2F%2Fa%7CB%3Ab')
            ->willReturn($cacheItemMock)
        ;

        $cacheItemMock
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function () use (&$savedParsedExpression) {
                return $savedParsedExpression;
            })
        ;

        $cacheItemMock
            ->expects($this->exactly(1))
            ->method('set')
            ->with($this->isInstanceOf(ParsedExpression::class))
            ->willReturnCallback(function ($parsedExpression) use (&$savedParsedExpression, $cacheItemMock) {
                $savedParsedExpression = $parsedExpression;

                return $cacheItemMock;
            })
        ;

        $cacheMock
            ->expects($this->exactly(1))
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
        $this->assertSame('in_array($foo->not, [0 => $bar])', $compiled);

        $result = $expressionLanguage->evaluate($expression, ['foo' => (object) ['not' => 'test'], 'bar' => 'test']);
        $this->assertTrue($result);
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterParse($registerCallback)
    {
        $this->expectException(\LogicException::class);
        $el = new ExpressionLanguage();
        $el->parse('1 + 1', []);
        $registerCallback($el);
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterEval($registerCallback)
    {
        $this->expectException(\LogicException::class);
        $el = new ExpressionLanguage();
        $el->evaluate('1 + 1');
        $registerCallback($el);
    }

    /**
     * @dataProvider provideNullSafe
     */
    public function testNullSafeEvaluate($expression, $foo)
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertNull($expressionLanguage->evaluate($expression, ['foo' => $foo]));
    }

    /**
     * @dataProvider provideNullSafe
     */
    public function testNullSafeCompile($expression, $foo)
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertNull(eval(sprintf('return %s;', $expressionLanguage->compile($expression, ['foo' => 'foo']))));
    }

    public function provideNullSafe()
    {
        $foo = new class() extends \stdClass {
            public function bar()
            {
                return null;
            }
        };

        yield ['foo?.bar', null];
        yield ['foo?.bar()', null];
        yield ['foo.bar?.baz', (object) ['bar' => null]];
        yield ['foo.bar?.baz()', (object) ['bar' => null]];
        yield ['foo["bar"]?.baz', ['bar' => null]];
        yield ['foo["bar"]?.baz()', ['bar' => null]];
        yield ['foo.bar()?.baz', $foo];
        yield ['foo.bar()?.baz()', $foo];

        yield ['foo?.bar.baz', null];
        yield ['foo?.bar["baz"]', null];
        yield ['foo?.bar["baz"]["qux"]', null];
        yield ['foo?.bar["baz"]["qux"].quux', null];
        yield ['foo?.bar["baz"]["qux"].quux()', null];
        yield ['foo?.bar().baz', null];
        yield ['foo?.bar()["baz"]', null];
        yield ['foo?.bar()["baz"]["qux"]', null];
        yield ['foo?.bar()["baz"]["qux"].quux', null];
        yield ['foo?.bar()["baz"]["qux"].quux()', null];
    }

    /**
     * @dataProvider provideInvalidNullSafe
     */
    public function testNullSafeEvaluateFails($expression, $foo, $message)
    {
        $expressionLanguage = new ExpressionLanguage();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
        $expressionLanguage->evaluate($expression, ['foo' => $foo]);
    }

    /**
     * @dataProvider provideInvalidNullSafe
     */
    public function testNullSafeCompileFails($expression, $foo)
    {
        $expressionLanguage = new ExpressionLanguage();

        $this->expectWarning();
        eval(sprintf('return %s;', $expressionLanguage->compile($expression, ['foo' => 'foo'])));
    }

    public function provideInvalidNullSafe()
    {
        yield ['foo?.bar.baz', (object) ['bar' => null], 'Unable to get property "baz" of non-object "foo.bar".'];
        yield ['foo?.bar["baz"]', (object) ['bar' => null], 'Unable to get an item of non-array "foo.bar".'];
        yield ['foo?.bar["baz"].qux.quux', (object) ['bar' => ['baz' => null]], 'Unable to get property "qux" of non-object "foo.bar["baz"]".'];
    }

    /**
     * @dataProvider getRegisterCallbacks
     */
    public function testRegisterAfterCompile($registerCallback)
    {
        $this->expectException(\LogicException::class);
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
