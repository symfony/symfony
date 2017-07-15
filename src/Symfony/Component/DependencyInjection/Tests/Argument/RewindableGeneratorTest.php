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
        $this->assertInstanceOf(\Countable::class, new RewindableGenerator(function ($k, $b = false) {
        }, function () {
            yield 1;
        }, 1));
    }

    public function testCountUsesProvidedValue()
    {
        $generator = new RewindableGenerator(function ($k, $b = false) {
        }, function () {
            yield 1;
        }, 3);

        $this->assertCount(3, $generator);
    }

    public function testCountUsesProvidedValueAsCallback()
    {
        $called = 0;
        $generator = new RewindableGenerator(function ($k, $b = false) {
        }, function () {
            yield 1;
        }, function () use (&$called) {
            ++$called;

            return 3;
        });

        $this->assertSame(0, $called, 'Count callback is called lazily');
        $this->assertCount(3, $generator);

        count($generator);

        $this->assertSame(1, $called, 'Count callback is called only once');
    }

    public function testPsrContainer()
    {
        $generator = new RewindableGenerator(function ($k, $b = false) {
            return $b ? 'a' === $k : ('a' === $k ? 0 : 1);
        }, function () {
            yield 'a' => 0;
        }, 1);

        $this->assertTrue($generator->has('a'));
        $this->assertFalse($generator->has('b'));
        $this->assertSame(0, $generator->get('a'));
        $this->assertSame(1, $generator->get('b'));
    }
}
