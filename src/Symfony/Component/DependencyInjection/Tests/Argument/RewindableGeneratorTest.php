<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;

class RewindableGeneratorTest extends TestCase
{
    public function testImplementsCountable()
    {
        self::assertInstanceOf(\Countable::class, new RewindableGenerator(function () {
            yield 1;
        }, 1));
    }

    public function testCountUsesProvidedValue()
    {
        $generator = new RewindableGenerator(function () {
            yield 1;
        }, 3);

        self::assertCount(3, $generator);
    }

    public function testCountUsesProvidedValueAsCallback()
    {
        $called = 0;
        $generator = new RewindableGenerator(function () {
            yield 1;
        }, function () use (&$called) {
            ++$called;

            return 3;
        });

        self::assertSame(0, $called, 'Count callback is called lazily');
        self::assertCount(3, $generator);

        \count($generator);

        self::assertSame(1, $called, 'Count callback is called only once');
    }
}
