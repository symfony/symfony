<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Iterator\LazyIterator;

class LazyIteratorTest extends TestCase
{
    public function testLazy()
    {
        new LazyIterator(function () {
            $this->markTestFailed('lazyIterator should not be called');
        });

        $this->expectNotToPerformAssertions();
    }

    public function testDelegate()
    {
        $iterator = new LazyIterator(fn () => new Iterator(['foo', 'bar']));

        $this->assertCount(2, iterator_to_array($iterator));
    }

    public function testInnerDestructedAtTheEnd()
    {
        $count = 0;
        $iterator = new LazyIterator(function () use (&$count) {
            ++$count;

            return new Iterator(['foo', 'bar']);
        });

        foreach ($iterator as $x) {
        }
        $this->assertSame(1, $count);
        foreach ($iterator as $x) {
        }
        $this->assertSame(2, $count);
    }
}
