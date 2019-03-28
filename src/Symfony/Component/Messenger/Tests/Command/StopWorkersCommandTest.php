<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Command;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Command\StopWorkersCommand;

class StopWorkersCommandTest extends TestCase
{
    public function testItSetsCacheItem()
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('set');
        $cachePool->expects($this->once())->method('getItem')->willReturn($cacheItem);
        $cachePool->expects($this->once())->method('save')->with($cacheItem);

        $command = new StopWorkersCommand($cachePool);

        $tester = new CommandTester($command);
        $tester->execute([]);
    }
}
