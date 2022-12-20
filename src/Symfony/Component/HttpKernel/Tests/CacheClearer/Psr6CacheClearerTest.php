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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

class Psr6CacheClearerTest extends TestCase
{
    public function testClearPoolsInjectedInConstructor()
    {
        $pool = self::createMock(CacheItemPoolInterface::class);
        $pool
            ->expects(self::once())
            ->method('clear');

        (new Psr6CacheClearer(['pool' => $pool]))->clear('');
    }

    public function testClearPool()
    {
        $pool = self::createMock(CacheItemPoolInterface::class);
        $pool
            ->expects(self::once())
            ->method('clear')
            ->willReturn(true)
        ;

        self::assertTrue((new Psr6CacheClearer(['pool' => $pool]))->clearPool('pool'));
    }

    public function testClearPoolThrowsExceptionOnUnreferencedPool()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Cache pool not found: "unknown"');
        (new Psr6CacheClearer())->clearPool('unknown');
    }
}
