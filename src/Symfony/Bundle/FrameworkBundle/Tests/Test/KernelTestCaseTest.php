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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCaseTest extends KernelTestCase
{
    private static string $baseDir;

    public static function setUpBeforeClass(): void
    {
        self::$baseDir = sys_get_temp_dir().'/sf_kernel_testcase_'.uniqid('', true);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        (new Filesystem())->remove(self::$baseDir);
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new SelfReferencingServiceKernel(self::$baseDir);
    }

    public function testEnsureKernelShutdownDestroysServicesThatAreInAReferenceCycle()
    {
        static::bootKernel()->getContainer()->get('self_referencing');
        SelfReferencingService::$destructed = false;

        $gcEnabled = gc_enabled();
        gc_disable();

        try {
            static::ensureKernelShutdown();

            $this->assertTrue(SelfReferencingService::$destructed);
        } finally {
            if ($gcEnabled) {
                gc_enable();
            }
        }
    }
}

class SelfReferencingServiceKernel extends Kernel
{
    public function __construct(
        private readonly string $baseDir,
    ) {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->register('kernel', KernelInterface::class)->setSynthetic(true)->setPublic(true);
            $container->register('self_referencing', SelfReferencingService::class)->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return $this->baseDir;
    }

    public function getCacheDir(): string
    {
        return $this->baseDir.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->baseDir.'/log';
    }
}

class SelfReferencingService
{
    public static bool $destructed = false;

    public self $self;

    public function __construct()
    {
        $this->self = $this;
    }

    public function __destruct()
    {
        self::$destructed = true;
    }
}
