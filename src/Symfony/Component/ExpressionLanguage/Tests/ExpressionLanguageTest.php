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
use Symfony\Component\ExpressionLanguage\Tests\Fixtures\FooBackedEnum;
use Symfony\Component\ExpressionLanguage\Tests\Fixtures\FooEnum;
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

    /**
     * @dataProvider basicPhpFunctionProvider
     */
    public function testBasicPhpFunction($expression, $expected, $compiled)
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertSame($expected, $expressionLanguage->evaluate($expression));
        $this->assertSame($compiled, $expressionLanguage->compile($expression));
    }

    public static function basicPhpFunctionProvider()
    {
        return [
            ['constant("PHP_VERSION")', \PHP_VERSION, '\constant("PHP_VERSION")'],
            ['min(1,2,3)', 1, '\min(1, 2, 3)'],
            ['max(1,2,3)', 3, '\max(1, 2, 3)'],
        ];
    }

    public function testEnumFunctionWithConstantThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('The string "PHP_VERSION" is not the name of a valid enum case.');
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->evaluate('enum("PHP_VERSION")');
    }

    public function testCompiledEnumFunctionWithConstantThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('The string "PHP_VERSION" is not the name of a valid enum case.');
        $expressionLanguage = new ExpressionLanguage();
        eval($expressionLanguage->compile('enum("PHP_VERSION")').';');
    }

    public function testEnumFunction()
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertSame(FooEnum::Foo, $expressionLanguage->evaluate('enum("Symfony\\\\Component\\\\ExpressionLanguage\\\\Tests\\\\Fixtures\\\\FooEnum::Foo")'));
    }

    public function testCompiledEnumFunction()
    {
        $result = null;
        $expressionLanguage = new ExpressionLanguage();
        eval(\sprintf('$result = %s;', $expressionLanguage->compile('enum("Symfony\\\\Component\\\\ExpressionLanguage\\\\Tests\\\\Fixtures\\\\FooEnum::Foo")')));

        $this->assertSame(FooEnum::Foo, $result);
    }

    public function testBackedEnumFunction()
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertSame(FooBackedEnum::Bar, $expressionLanguage->evaluate('enum("Symfony\\\\Component\\\\ExpressionLanguage\\\\Tests\\\\Fixtures\\\\FooBackedEnum::Bar")'));
        $this->assertSame('Foo', $expressionLanguage->evaluate('enum("Symfony\\\\Component\\\\ExpressionLanguage\\\\Tests\\\\Fixtures\\\\FooBackedEnum::Bar").value'));
    }

    public function testCompiledEnumFunctionWithBackedEnum()
    {
        $result = null;
        $expressionLanguage = new ExpressionLanguage();
        eval(\sprintf('$result = %s;', $expressionLanguage->compile('enum("Symfony\\\\Component\\\\ExpressionLanguage\\\\Tests\\\\Fixtures\\\\FooBackedEnum::Bar")')));

        $this->assertSame(FooBackedEnum::Bar, $result);
    }

    /**
     * @dataProvider providerTestCases
     */
    public function testProviders(iterable $providers)
    {
        $expressionLanguage = new ExpressionLanguage(null, $providers);
        $this->assertSame('foo', $expressionLanguage->evaluate('identity("foo")'));
        $this->assertSame('"foo"', $expressionLanguage->compile('identity("foo")'));
        $this->assertSame('FOO', $expressionLanguage->evaluate('strtoupper("foo")'));
        $this->assertSame('\strtoupper("foo")', $expressionLanguage->compile('strtoupper("foo")'));
        $this->assertSame('foo', $expressionLanguage->evaluate('strtolower("FOO")'));
        $this->assertSame('\strtolower("FOO")', $expressionLanguage->compile('strtolower("FOO")'));
        $this->assertTrue($expressionLanguage->evaluate('fn_namespaced()'));
        $this->assertSame('\Symfony\Component\ExpressionLanguage\Tests\Fixtures\fn_namespaced()', $expressionLanguage->compile('fn_namespaced()'));
    }

    public static function providerTestCases(): iterable
    {
        yield 'array' => [[new TestProvider()]];
        yield 'Traversable' => [(function () {
            yield new TestProvider();
        })()];
    }

    /**
     * @dataProvider shortCircuitProviderEvaluate
     */
    public function testShortCircuitOperatorsEvaluate($expression, array $values, $expected)
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertSame($expected, $expressionLanguage->evaluate($expression, $values));
    }

    /**
     * @dataProvider shortCircuitProviderCompile
     */
    public function testShortCircuitOperatorsCompile($expression, array $names, $expected)
    {
        $result = null;
        $expressionLanguage = new ExpressionLanguage();
        eval(\sprintf('$result = %s;', $expressionLanguage->compile($expression, $names)));
        $this->assertSame($expected, $result);
    }

    public function testParseThrowsInsteadOfNotice()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unexpected end of expression around position 6 for expression `node.`.');
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->parse('node.', ['node']);
    }

    public function testParseReturnsObjectOnAlreadyParsedExpression()
    {
        $expressionLanguage = new ExpressionLanguage();
        $expression = $expressionLanguage->parse('1 + 1', []);

        $this->assertSame($expression, $expressionLanguage->parse($expression, []));
    }

    public static function shortCircuitProviderEvaluate()
    {
        $object = new class(static::fail(...)) {
            private \Closure $fail;

            public function __construct(\Closure $fail)
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

    public static function shortCircuitProviderCompile()
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
        $this->assertSame('\in_array($foo->not, [0 => $bar], true)', $compiled);

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
        $this->assertNull(eval(\sprintf('return %s;', $expressionLanguage->compile($expression, ['foo' => 'foo']))));
    }

    public static function provideNullSafe()
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

        $this->expectException(\ErrorException::class);

        set_error_handler(static function (int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool {
            if ($errno & (\E_WARNING | \E_USER_WARNING) && (str_contains($errstr, 'Attempt to read property') || str_contains($errstr, 'Trying to access'))) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }

            return false;
        });

        try {
            eval(\sprintf('return %s;', $expressionLanguage->compile($expression, ['foo' => 'foo'])));
        } finally {
            restore_error_handler();
        }
    }

    public static function provideInvalidNullSafe()
    {
        yield ['foo?.bar.baz', (object) ['bar' => null], 'Unable to get property "baz" of non-object "foo.bar".'];
        yield ['foo?.bar["baz"]', (object) ['bar' => null], 'Unable to get an item of non-array "foo.bar".'];
        yield ['foo?.bar["baz"].qux.quux', (object) ['bar' => ['baz' => null]], 'Unable to get property "qux" of non-object "foo.bar["baz"]".'];
    }

    /**
     * @dataProvider provideNullCoalescing
     */
    public function testNullCoalescingEvaluate($expression, $foo)
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertSame($expressionLanguage->evaluate($expression, ['foo' => $foo]), 'default');
    }

    /**
     * @dataProvider provideNullCoalescing
     */
    public function testNullCoalescingCompile($expression, $foo)
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertSame(eval(\sprintf('return %s;', $expressionLanguage->compile($expression, ['foo' => 'foo']))), 'default');
    }

    public static function provideNullCoalescing()
    {
        $foo = new class() extends \stdClass {
            public function bar()
            {
                return null;
            }
        };

        yield ['bar ?? "default"', null];
        yield ['foo.bar ?? "default"', null];
        yield ['foo.bar.baz ?? "default"', (object) ['bar' => null]];
        yield ['foo.bar ?? foo.baz ?? "default"', null];
        yield ['foo[0] ?? "default"', []];
        yield ['foo["bar"] ?? "default"', ['bar' => null]];
        yield ['foo["baz"] ?? "default"', ['bar' => null]];
        yield ['foo["bar"]["baz"] ?? "default"', ['bar' => null]];
        yield ['foo["bar"].baz ?? "default"', ['bar' => null]];
        yield ['foo.bar().baz ?? "default"', $foo];
        yield ['foo.bar.baz.bam ?? "default"', (object) ['bar' => null]];
        yield ['foo?.bar?.baz?.qux ?? "default"', (object) ['bar' => null]];
        yield ['foo[123][456][789] ?? "default"', [123 => []]];
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

    public static function validCommentProvider()
    {
        yield ['1 /* comment */ + 1'];
        yield ['1 /* /* comment with spaces */'];
        yield ['1 /** extra stars **/ + 1'];
        yield ["/* multi\nline */ 'foo'"];
    }

    /**
     * @dataProvider validCommentProvider
     */
    public function testLintAllowsComments($expression)
    {
        $el = new ExpressionLanguage();
        $el->lint($expression, []);

        $this->expectNotToPerformAssertions();
    }

    public static function invalidCommentProvider()
    {
        yield ['1 + no start */'];
        yield ['1 /* no closing'];
        yield ['1 /* double closing */ */'];
    }

    /**
     * @dataProvider invalidCommentProvider
     */
    public function testLintThrowsOnInvalidComments($expression)
    {
        $el = new ExpressionLanguage();

        $this->expectException(SyntaxError::class);
        $el->lint($expression, []);
    }

    public function testLintDoesntThrowOnValidExpression()
    {
        $el = new ExpressionLanguage();
        $el->lint('1 + 1', []);

        $this->expectNotToPerformAssertions();
    }

    public function testLintThrowsOnInvalidExpression()
    {
        $el = new ExpressionLanguage();

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unexpected end of expression around position 6 for expression `node.`.');

        $el->lint('node.', ['node']);
    }

    public function testCommentsIgnored()
    {
        $expressionLanguage = new ExpressionLanguage();
        $this->assertSame(3, $expressionLanguage->evaluate('1 /* foo */ + 2'));
        $this->assertSame('(1 + 2)', $expressionLanguage->compile('1 /* foo */ + 2'));
    }

    public static function getRegisterCallbacks()
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
