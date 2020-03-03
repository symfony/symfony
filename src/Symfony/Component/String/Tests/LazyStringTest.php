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

        $this->assertSame(0, $count);
        $this->assertSame('1', (string) $s);
        $this->assertSame(1, $count);
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

        $this->assertSame(0, $count);
        $this->assertSame('1', (string) $s);
        $this->assertSame(1, $count);
        $this->assertSame('1', (string) $s); // ensure the value is memoized
        $this->assertSame(1, $count);
    }

    /**
     * @runInSeparateProcess
     */
    public function testReturnTypeError()
    {
        ErrorHandler::register();

        $s = LazyString::fromCallable(function () { return []; });

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Return value of '.__NAMESPACE__.'\{closure}() passed to '.LazyString::class.'::fromCallable() must be of the type string, array returned.');

        (string) $s;
    }

    public function testFromStringable()
    {
        $this->assertInstanceOf(LazyString::class, LazyString::fromStringable('abc'));
        $this->assertSame('abc', (string) LazyString::fromStringable('abc'));
        $this->assertSame('1', (string) LazyString::fromStringable(true));
        $this->assertSame('', (string) LazyString::fromStringable(false));
        $this->assertSame('123', (string) LazyString::fromStringable(123));
        $this->assertSame('123.456', (string) LazyString::fromStringable(123.456));
        $this->assertStringContainsString('hello', (string) LazyString::fromStringable(new \Exception('hello')));
    }

    public function testResolve()
    {
        $this->assertSame('abc', LazyString::resolve('abc'));
        $this->assertSame('1', LazyString::resolve(true));
        $this->assertSame('', LazyString::resolve(false));
        $this->assertSame('123', LazyString::resolve(123));
        $this->assertSame('123.456', LazyString::resolve(123.456));
        $this->assertStringContainsString('hello', LazyString::resolve(new \Exception('hello')));
    }

    public function testIsStringable()
    {
        $this->assertTrue(LazyString::isStringable('abc'));
        $this->assertTrue(LazyString::isStringable(true));
        $this->assertTrue(LazyString::isStringable(false));
        $this->assertTrue(LazyString::isStringable(123));
        $this->assertTrue(LazyString::isStringable(123.456));
        $this->assertTrue(LazyString::isStringable(new \Exception('hello')));
    }

    public function testIsNotStringable()
    {
        $this->assertFalse(LazyString::isStringable(null));
        $this->assertFalse(LazyString::isStringable([]));
        $this->assertFalse(LazyString::isStringable(STDIN));
        $this->assertFalse(LazyString::isStringable(new \StdClass()));
        if (\PHP_VERSION_ID < 80000) {
            $this->assertFalse(LazyString::isStringable(@eval('return new class() {private function __toString() {}};')));
        }
    }
}
