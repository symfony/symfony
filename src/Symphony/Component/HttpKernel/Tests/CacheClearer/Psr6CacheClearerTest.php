<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\CacheClearer;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Psr\Cache\CacheItemPoolInterface;

class Psr6CacheClearerTest extends TestCase
{
    public function testClearPoolsInjectedInConstructor()
    {
        $pool = $this->getMockBuilder(CacheItemPoolInterface::class)->getMock();
        $pool
            ->expects($this->once())
            ->method('clear');

        (new Psr6CacheClearer(array('pool' => $pool)))->clear('');
    }

    public function testClearPool()
    {
        $pool = $this->getMockBuilder(CacheItemPoolInterface::class)->getMock();
        $pool
            ->expects($this->once())
            ->method('clear');

        (new Psr6CacheClearer(array('pool' => $pool)))->clearPool('pool');
    }

    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Cache pool not found: unknown
     */
    public function testClearPoolThrowsExceptionOnUnreferencedPool()
    {
        (new Psr6CacheClearer())->clearPool('unknown');
    }
}
