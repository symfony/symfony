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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCaseShutdownTest extends KernelTestCase
{
    private static string $baseDir;

    public static function setUpBeforeClass(): void
    {
        self::$baseDir = sys_get_temp_dir().'/sf_kernel_testcase_shutdown_'.uniqid('', true);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$baseDir)) {
            (new Filesystem())->remove(self::$baseDir);
        }

        parent::tearDownAfterClass();
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new ReferenceCycleTestKernel(self::$baseDir, $options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testEnsureKernelShutdownDestroysServicesThatAreInAReferenceCycle()
    {
        $gcEnabled = gc_enabled();
        gc_disable();

        try {
            $kernel = static::createKernel();
            $kernel->boot();
            $kernel->getContainer()->get('self_referencing');
            SelfReferencingService::$destructed = false;
            $kernel->shutdown();

            if (!SelfReferencingService::$destructed) {
                $this->markTestSkipped('This version of symfony/http-kernel does not collect garbage cycles when the kernel shuts down.');
            }

            static::bootKernel()->getContainer()->get('self_referencing');
            SelfReferencingService::$destructed = false;
            static::ensureKernelShutdown();

            $this->assertTrue(SelfReferencingService::$destructed);
        } finally {
            if ($gcEnabled) {
                gc_enable();
            }
        }
    }
}

class ReferenceCycleTestKernel extends Kernel
{
    public function __construct(
        private readonly string $baseDir,
        string $environment,
        bool $debug,
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->register('kernel', KernelInterface::class)
                ->setSynthetic(true)
                ->setPublic(true);

            $container->register('self_referencing', SelfReferencingService::class)
                ->setPublic(true)
                ->setProperty('self', new Reference('self_referencing'));
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

    public ?self $self = null;

    public function __destruct()
    {
        self::$destructed = true;
    }
}
