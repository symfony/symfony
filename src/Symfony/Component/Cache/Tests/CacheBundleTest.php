<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheBundle;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

class CacheBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_cache_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheDefaultPoolsAreRegistered()
    {
        $container = $this->boot();

        $this->assertInstanceOf(FilesystemAdapter::class, $container->get('test.cache.app'));
        $this->assertInstanceOf(AdapterInterface::class, $container->get('test.cache.system'));
        $this->assertInstanceOf(TagAwareAdapter::class, $container->get('test.cache.app.taggable'));
        $this->assertInstanceOf(Psr6CacheClearer::class, $container->get('test.cache.global_clearer'));
    }

    public function testAConfiguredPoolIsUsable()
    {
        $pool = $this->boot(['pools' => ['cache.acme' => ['adapters' => 'cache.adapter.array', 'public' => true]]])->get('cache.acme');

        $this->assertInstanceOf(ArrayAdapter::class, $pool);

        $item = $pool->getItem('foo');
        $pool->save($item->set('bar'));

        $this->assertSame('bar', $pool->getItem('foo')->get());
    }

    public function testTheEarlyExpirationHandlerIsDroppedWhenNoPoolUsesIt()
    {
        $this->assertFalse($this->boot()->has('cache.early_expiration_handler'));
    }

    private function boot(array $config = []): object
    {
        $kernel = new TestCacheKernel('test', false, $this->varDir.'/'.md5(serialize($config)), $config);
        $kernel->boot();

        return $kernel->getContainer();
    }
}

class TestCacheKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private array $config)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new CacheBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('cache', $this->config);

        $container->services()
            ->alias('test.cache.app', 'cache.app')->public()
            ->alias('test.cache.system', 'cache.system')->public()
            ->alias('test.cache.app.taggable', 'cache.app.taggable')->public()
            ->alias('test.cache.global_clearer', 'cache.global_clearer')->public()
        ;
    }
}
