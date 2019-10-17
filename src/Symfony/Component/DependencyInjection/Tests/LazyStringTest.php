<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\LazyString;
use Symfony\Component\ErrorHandler\ErrorHandler;

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
}
