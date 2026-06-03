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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCaseHttpCacheTest extends KernelTestCase
{
    private static string $baseDir;

    public static function setUpBeforeClass(): void
    {
        self::$baseDir = sys_get_temp_dir().'/sf_http_cache_kernel_testcase_'.uniqid('', true);
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
        return new HttpCacheTestKernel(self::$baseDir, $options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testHttpCacheIsClearedBetweenKernelShutdowns()
    {
        DynamicHttpKernel::$counter = 0;

        $kernel = $this->bootKernelForHttpCache();
        $response = $kernel->handle(Request::create('/'));

        $this->assertSame('count: 1', $response->getContent());

        static::ensureKernelShutdown();

        $kernel = $this->bootKernelForHttpCache();
        $response = $kernel->handle(Request::create('/'));

        $this->assertSame('count: 2', $response->getContent());
    }

    private function bootKernelForHttpCache(): KernelInterface
    {
        $kernel = static::createKernel();
        $kernel->boot();
        static::$kernel = $kernel;
        static::$booted = true;

        return $kernel;
    }
}

class HttpCacheTestKernel extends Kernel
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

    public function registerContainerConfiguration(\Symfony\Component\Config\Loader\LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->register('kernel', KernelInterface::class)
                ->setSynthetic(true)
                ->setPublic(true);

            $container->register('http_kernel', DynamicHttpKernel::class)
                ->setPublic(true);

            $container->register('http_cache.store', Store::class)
                ->setPublic(true)
                ->setArguments([$this->getCacheDir().'/http_cache']);

            $container->register('http_cache', HttpCache::class)
                ->setPublic(true)
                ->setArguments([
                    new Reference('kernel'),
                    new Reference('http_cache.store'),
                    null,
                    [],
                ]);
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

class DynamicHttpKernel implements HttpKernelInterface
{
    public static int $counter = 0;

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        $response = new Response('count: '.++self::$counter);
        $response->setPublic();
        $response->setMaxAge(60);

        return $response;
    }
}
