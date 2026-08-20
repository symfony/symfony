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
use Symfony\Component\Process\Process;

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

    public function testWarmupRecoversFromCorruptedDeprecationLog()
    {
        $logFile = tempnam(sys_get_temp_dir(), 'sf_deprecations_');
        file_put_contents($logFile, 'a:0:{}stale-bytes-from-a-torn-write');

        // collecting deprecations is disabled when PHPUNIT_COMPOSER_INSTALL is defined, hence the child process
        $srcDir = \dirname((new \ReflectionClass(CacheWarmerAggregate::class))->getFileName());
        $bootCode = <<<'EOPHP'
            <?php
            [, $srcDir, $logFile] = $argv;
            require $srcDir.'/WarmableInterface.php';
            require $srcDir.'/CacheWarmerInterface.php';
            require $srcDir.'/CacheWarmerAggregate.php';

            set_error_handler(static function ($type, $message, $file, $line) {
                throw new \ErrorException($message, 0, $type, $file, $line);
            });

            (new \Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate([], true, $logFile))->warmUp(sys_get_temp_dir());

            echo 'OK';
            EOPHP;

        $process = new Process([\PHP_BINARY, '--', $srcDir, $logFile]);
        $process->setInput($bootCode);
        $process->run();

        try {
            $this->assertSame('OK', $process->getOutput(), $process->getErrorOutput());
            $this->assertSame(0, $process->getExitCode());
            $this->assertSame([], unserialize(file_get_contents($logFile), ['allowed_classes' => false]));
        } finally {
            @unlink($logFile);
        }
    }
}
