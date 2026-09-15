<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\SortedListeners;

class SortedListenersTest extends TestCase
{
    public function testEmpty()
    {
        $listeners = new SortedListeners();

        $this->assertTrue($listeners->isEmpty());
        $this->assertSame([], $listeners->toArray());
    }

    public function testListenersComeOutByDescendingPriority()
    {
        $listeners = (new SortedListeners())
            ->add('foo', 'low', -10)
            ->add('foo', 'default')
            ->add('foo', 'high', 10);

        $this->assertFalse($listeners->isEmpty());
        $this->assertSame(['foo' => [10 => ['high'], 0 => ['default'], -10 => ['low']]], $listeners->toArray());
    }

    public function testListenersOfTheSamePriorityKeepTheirOrder()
    {
        $listeners = (new SortedListeners())
            ->add('foo', 'first')
            ->add('foo', 'second')
            ->add('bar', 'third');

        $this->assertSame(['foo' => [0 => ['first', 'second']], 'bar' => [0 => ['third']]], $listeners->toArray());
    }
}
