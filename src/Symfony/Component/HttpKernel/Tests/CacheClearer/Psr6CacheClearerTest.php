<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\CacheClearer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
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

    /**
     * @group legacy
     * @expectedDeprecation The Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer::addPool() method is deprecated since Symfony 3.3 and will be removed in 4.0. Pass an array of pools indexed by name to the constructor instead.
     */
    public function testClearPoolsInjectedByAdder()
    {
        $pool1 = $this->getMockBuilder(CacheItemPoolInterface::class)->getMock();
        $pool1
            ->expects($this->once())
            ->method('clear');

        $pool2 = $this->getMockBuilder(CacheItemPoolInterface::class)->getMock();
        $pool2
            ->expects($this->once())
            ->method('clear');

        $clearer = new Psr6CacheClearer(array('pool1' => $pool1));
        $clearer->addPool($pool2);
        $clearer->clear('');
    }
}
