<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\WorkerRestarter;

class WorkerRestarterTest extends TestCase
{
    public function testRestartAllWorkers()
    {
        $this->assertSame(['workers.restart_requested_timestamp'], $this->restart());
    }

    public function testRestartTheWorkersOfSomeTransports()
    {
        $this->assertSame(['workers.restart_requested_timestamp.scheduler_default', 'workers.restart_requested_timestamp.app%3Aemails'], $this->restart('scheduler_default', 'app:emails'));
    }

    /**
     * @return list<string> The keys of the saved items
     */
    private function restart(string ...$transportNames): array
    {
        $savedKeys = [];
        $cachePool = $this->createStub(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturnCallback(function (string $key) {
            $item = $this->createMock(CacheItemInterface::class);
            $item->method('getKey')->willReturn($key);
            $item->expects($this->once())->method('set')->with($this->isFloat())->willReturnSelf();

            return $item;
        });
        $cachePool->method('save')->willReturnCallback(static function (CacheItemInterface $item) use (&$savedKeys) {
            $savedKeys[] = $item->getKey();

            return true;
        });

        new WorkerRestarter($cachePool)(...$transportNames);

        return $savedKeys;
    }
}
