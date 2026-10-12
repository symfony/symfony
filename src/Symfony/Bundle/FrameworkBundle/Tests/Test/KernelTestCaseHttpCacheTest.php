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

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        return new HttpCacheTestKernel(self::$baseDir, $options['http_cache'] ?? true, $options['store_dir'] ?? null, $options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testHttpCacheIsClearedBetweenKernelShutdowns()
    {
        DynamicHttpKernel::$counter = 0;

        $kernel = static::bootKernel();

        $this->assertSame('count: 1', $kernel->handle(Request::create('/'))->getContent());
        $this->assertSame('count: 1', $kernel->handle(Request::create('/'))->getContent());

        static::ensureKernelShutdown();

        $kernel = static::bootKernel();

        $this->assertSame('count: 2', $kernel->handle(Request::create('/'))->getContent());
    }

    public function testHttpCacheStoredOutsideTheShareDirIsCleared()
    {
        DynamicHttpKernel::$counter = 0;
        $options = ['environment' => 'store_dir', 'store_dir' => self::$baseDir.'/http_cache_store'];

        $kernel = static::bootKernel($options);

        $this->assertSame('count: 1', $kernel->handle(Request::create('/'))->getContent());
        $this->assertSame('count: 1', $kernel->handle(Request::create('/'))->getContent());
        $this->assertNotSame(['.', '..'], scandir(self::$baseDir.'/http_cache_store'));

        static::ensureKernelShutdown();

        $kernel = static::bootKernel($options);

        $this->assertSame('count: 2', $kernel->handle(Request::create('/'))->getContent());
    }

    public function testHttpCacheStoreIsNotCreatedWhenTheHttpCacheIsDisabled()
    {
        DynamicHttpKernel::$counter = 0;

        $kernel = static::bootKernel(['environment' => 'no_http_cache', 'http_cache' => false]);
        $shareDir = $kernel->getShareDir();

        $this->assertSame('count: 1', $kernel->handle(Request::create('/'))->getContent());

        static::ensureKernelShutdown();

        $this->assertDirectoryDoesNotExist($shareDir.'/http_cache');
    }
}

class HttpCacheTestKernel extends Kernel
{
    public function __construct(
        private readonly string $baseDir,
        private readonly bool $httpCache,
        private readonly ?string $storeDir,
        string $environment,
        bool $debug,
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'http_cache' => $this->httpCache,
            ]);

            $container->register('http_kernel', DynamicHttpKernel::class)
                ->setPublic(true);
        });
    }

    protected function build(ContainerBuilder $container): void
    {
        if (null !== $this->storeDir) {
            $container->addCompilerPass(new HttpCacheStoreDirPass($this->storeDir));
        }
    }

    public function getProjectDir(): string
    {
        return $this->baseDir;
    }

    public function getCacheDir(): string
    {
        return $this->baseDir.'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->baseDir.'/log';
    }
}

class HttpCacheStoreDirPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $storeDir,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('http_cache.store')->replaceArgument(0, $this->storeDir);
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
