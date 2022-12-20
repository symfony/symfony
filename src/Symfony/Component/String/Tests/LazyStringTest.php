<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\String\LazyString;

class LazyStringTest extends TestCase
{
    public function testLazyString()
    {
        $count = 0;
        $s = LazyString::fromCallable(function () use (&$count) {
            return ++$count;
        });

        self::assertSame(0, $count);
        self::assertSame('1', (string) $s);
        self::assertSame(1, $count);
    }

    public function testLazyCallable()
    {
        $count = 0;
        $s = LazyString::fromCallable([function () use (&$count) {
            return new class($count) {
                private $count;

                public function __construct(int &$count)
                {
                    $this->count = &$count;
                }

                public function __invoke()
                {
                    return ++$this->count;
                }
            };
        }]);

        self::assertSame(0, $count);
        self::assertSame('1', (string) $s);
        self::assertSame(1, $count);
        self::assertSame('1', (string) $s); // ensure the value is memoized
        self::assertSame(1, $count);
    }

    /**
     * @runInSeparateProcess
     */
    public function testReturnTypeError()
    {
        ErrorHandler::register();

        $s = LazyString::fromCallable(function () { return []; });

        self::expectException(\TypeError::class);
        self::expectExceptionMessage('Return value of '.__NAMESPACE__.'\{closure}() passed to '.LazyString::class.'::fromCallable() must be of the type string, array returned.');

        (string) $s;
    }

    public function testFromStringable()
    {
        self::assertInstanceOf(LazyString::class, LazyString::fromStringable('abc'));
        self::assertSame('abc', (string) LazyString::fromStringable('abc'));
        self::assertSame('1', (string) LazyString::fromStringable(true));
        self::assertSame('', (string) LazyString::fromStringable(false));
        self::assertSame('123', (string) LazyString::fromStringable(123));
        self::assertSame('123.456', (string) LazyString::fromStringable(123.456));
        self::assertStringContainsString('hello', (string) LazyString::fromStringable(new \Exception('hello')));
    }

    public function testResolve()
    {
        self::assertSame('abc', LazyString::resolve('abc'));
        self::assertSame('1', LazyString::resolve(true));
        self::assertSame('', LazyString::resolve(false));
        self::assertSame('123', LazyString::resolve(123));
        self::assertSame('123.456', LazyString::resolve(123.456));
        self::assertStringContainsString('hello', LazyString::resolve(new \Exception('hello')));
    }

    public function testIsStringable()
    {
        self::assertTrue(LazyString::isStringable('abc'));
        self::assertTrue(LazyString::isStringable(true));
        self::assertTrue(LazyString::isStringable(false));
        self::assertTrue(LazyString::isStringable(123));
        self::assertTrue(LazyString::isStringable(123.456));
        self::assertTrue(LazyString::isStringable(new \Exception('hello')));
    }

    public function testIsNotStringable()
    {
        self::assertFalse(LazyString::isStringable(null));
        self::assertFalse(LazyString::isStringable([]));
        self::assertFalse(LazyString::isStringable(\STDIN));
        self::assertFalse(LazyString::isStringable(new \stdClass()));
    }
}
