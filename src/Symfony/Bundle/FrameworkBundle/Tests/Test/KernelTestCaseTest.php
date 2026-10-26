<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Test;

require_once __DIR__.'/../Fixtures/gc-function-mock.php';

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\GcFunctionMock;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCaseTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        GcFunctionMock::$status = null;

        parent::tearDown();
    }

    #[TestWith([5000, 0])]
    #[TestWith([5001, 1])]
    public function testEnsureKernelShutdownCollectsCyclesWhenTheRootBufferIsHalfFull(int $roots, int $collections)
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getContainer')->willReturn(new Container());
        $kernel->expects($this->once())->method('shutdown');
        static::$kernel = $kernel;
        GcFunctionMock::$status = ['roots' => $roots, 'threshold' => 10001];
        GcFunctionMock::$collections = 0;

        static::ensureKernelShutdown();
        static::$kernel = null;

        $this->assertSame($collections, GcFunctionMock::$collections);
    }

    #[BackupGlobals(true)]
    public function testGetKernelClassReadsServerBeforeEnv()
    {
        $_SERVER['KERNEL_CLASS'] = EnvTestKernel::class;
        $_ENV['KERNEL_CLASS'] = Kernel::class;

        $this->assertSame(EnvTestKernel::class, static::getKernelClass());
    }

    #[BackupGlobals(true)]
    public function testCreateKernelReadsServerBeforeEnv()
    {
        $_SERVER['KERNEL_CLASS'] = EnvTestKernel::class;
        $_SERVER['APP_ENV'] = 'test';
        $_SERVER['APP_DEBUG'] = '0';
        $_ENV['APP_ENV'] = 'dev';
        $_ENV['APP_DEBUG'] = '1';

        $kernel = static::createKernel();

        $this->assertSame('test', $kernel->getEnvironment());
        $this->assertFalse($kernel->isDebug());
    }

    #[BackupGlobals(true)]
    public function testCreateKernelFallsBackToEnv()
    {
        unset($_SERVER['KERNEL_CLASS'], $_SERVER['APP_ENV'], $_SERVER['APP_DEBUG']);
        $_ENV['KERNEL_CLASS'] = EnvTestKernel::class;
        $_ENV['APP_ENV'] = 'dev';
        $_ENV['APP_DEBUG'] = '0';

        $kernel = static::createKernel();

        $this->assertInstanceOf(EnvTestKernel::class, $kernel);
        $this->assertSame('dev', $kernel->getEnvironment());
        $this->assertFalse($kernel->isDebug());
    }
}

class EnvTestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }
}
