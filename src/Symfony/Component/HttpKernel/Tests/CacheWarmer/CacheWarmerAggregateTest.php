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
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class CacheWarmerAggregateTest extends TestCase
{
    public function testInjectWarmersUsingConstructor()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $warmer
            ->expects($this->once())
            ->method('warmUp');
        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->warmUp(__DIR__);
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
        $aggregate->warmUp(__DIR__);
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
        $aggregate->warmUp(__DIR__);
    }

    public function testWarmupReturnsFilesOrClasses()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $warmer
            ->expects($this->never())
            ->method('isOptional');
        $warmer
            ->expects($this->once())
            ->method('warmUp')
            ->willReturn([__CLASS__, __FILE__]);

        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->enableOptionalWarmers();

        $this->assertSame([__CLASS__, __FILE__], $aggregate->warmUp(__DIR__));
    }

    public function testWarmupChecksInvalidFiles()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $warmer
            ->expects($this->never())
            ->method('isOptional');
        $warmer
            ->expects($this->once())
            ->method('warmUp')
            ->willReturn([self::class, __DIR__]);

        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->enableOptionalWarmers();

        $this->expectException(\LogicException::class);
        $aggregate->warmUp(__DIR__);
    }

    public function testWarmupPassBuildDir()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $warmer
            ->expects($this->once())
            ->method('warmUp')
            ->with('cache_dir', 'build_dir');

        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->enableOptionalWarmers();
        $aggregate->warmUp('cache_dir', 'build_dir');
    }

    public function testWarmupOnOptionalWarmerPassBuildDir()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $warmer
            ->expects($this->once())
            ->method('isOptional')
            ->willReturn(true);
        $warmer
            ->expects($this->once())
            ->method('warmUp')
            ->with('cache_dir', 'build_dir');

        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->enableOnlyOptionalWarmers();
        $aggregate->warmUp('cache_dir', 'build_dir');
    }

    public function testWarmupWhenDebugDisplaysWarmupDuration()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $io
            ->expects($this->once())
            ->method('isDebug')
            ->willReturn(true)
        ;

        $io
            ->expects($this->once())
            ->method('info')
            ->with($this->matchesRegularExpression('/"(.+)" completed in (.+)ms\./'))
        ;

        $warmer
            ->expects($this->once())
            ->method('warmUp');

        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->warmUp(__DIR__, null, $io);
    }

    public function testWarmupWhenNotDebugDoesntDisplayWarmupDuration()
    {
        $warmer = $this->createMock(CacheWarmerInterface::class);
        $io = $this->createMock(SymfonyStyle::class);

        $io
            ->expects($this->once())
            ->method('isDebug')
            ->willReturn(false)
        ;

        $io
            ->expects($this->never())
            ->method('info')
            ->with($this->matchesRegularExpression('/"(.+)" completed in (.+)ms\./'))
        ;

        $warmer
            ->expects($this->once())
            ->method('warmUp');

        $aggregate = new CacheWarmerAggregate([$warmer]);
        $aggregate->warmUp(__DIR__, null, $io);
    }
}
