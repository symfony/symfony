<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class CacheWarmerAggregateTest extends TestCase
{
    protected static $cacheDir;

    public static function setUpBeforeClass(): void
    {
        self::$cacheDir = tempnam(sys_get_temp_dir(), 'sf_cache_warmer_dir');
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$cacheDir);
    }

    public function testInjectWarmersUsingConstructor()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $warmer
            ->expects($this->once())
            ->method('warmUp');
        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->warmUp(self::$cacheDir);
    }

    public function testWarmupDoesCallWarmupOnOptionalWarmersWhenEnableOptionalWarmersIsEnabled()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $warmer
            ->expects($this->never())
            ->method('isOptional');
        $warmer
            ->expects($this->once())
            ->method('warmUp');

        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->enableOptionalWarmers();
        $aggregate->warmUp(self::$cacheDir);
    }

    public function testWarmupDoesNotCallWarmupOnOptionalWarmersWhenEnableOptionalWarmersIsNotEnabled()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $warmer
            ->expects($this->once())
            ->method('isOptional')
            ->willReturn(true);
        $warmer
            ->expects($this->never())
            ->method('warmUp');

        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->warmUp(self::$cacheDir);
    }
}
