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
use Symfony\Component\Cache\CachePoolRefresher;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Contracts\Cache\ItemInterface;

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

    public function testTheAppPoolIsAlwaysRefreshableAndTheSystemPoolNeverIs()
    {
        $container = $this->boot();
        $refresher = $container->get('test.cache.refresher');
        $app = $container->get('test.cache.app');
        $system = $container->get('test.cache.system');

        $app->get('key', static fn () => 'app');
        $system->get('key', static fn () => 'system');

        $refresher->enableRefresh();

        $this->assertSame('app/refreshed', $app->get('key', static fn () => 'app/refreshed'));
        $this->assertSame('system', $system->get('key', static fn () => 'system/refreshed'));

        $refresher->enableRefresh(false);

        $this->assertSame('app/refreshed', $app->get('key', static fn () => 'nope'));
    }

    public function testAnAdditionalPoolHasToOptIn()
    {
        $container = $this->boot(['pools' => [
            'cache.in' => ['adapters' => 'cache.adapter.array', 'public' => true, 'refreshable' => true],
            'cache.out' => ['adapters' => 'cache.adapter.array', 'public' => true],
        ]]);
        $in = $container->get('cache.in');
        $out = $container->get('cache.out');

        $in->get('key', static fn () => 'in');
        $out->get('key', static fn () => 'out');

        $container->get('test.cache.refresher')->enableRefresh();

        $this->assertSame('in/refreshed', $in->get('key', static fn () => 'in/refreshed'));
        $this->assertSame('out', $out->get('key', static fn () => 'out/refreshed'));
    }

    public function testRefreshingTheAppPoolLeavesTagVersionsAlone()
    {
        // the default taggable app pool keeps its tag versions inside "cache.app" itself
        $container = $this->boot();
        $taggable = $container->get('test.cache.app.taggable');
        $refresher = $container->get('test.cache.refresher');
        $tag = static fn (string $value) => static function (ItemInterface $item) use ($value) {
            $item->tag('shared');

            return $value;
        };

        $taggable->get('one', $tag('one'));
        $taggable->get('two', $tag('two'));

        $refresher->enableRefresh();

        $this->assertSame('one/refreshed', $taggable->get('one', $tag('one/refreshed')));

        $refresher->enableRefresh(false);

        $this->assertSame('two', $taggable->get('two', $tag('RECOMPUTED')));
    }

    public function testRefreshingATaggablePoolLeavesItsTagVersionsAlone()
    {
        $container = $this->boot(['pools' => [
            'cache.tagged' => ['adapters' => 'cache.adapter.array', 'tags' => true, 'public' => true, 'refreshable' => true],
        ]]);
        $pool = $container->get('cache.tagged');
        $tag = static fn (string $value) => static function (ItemInterface $item) use ($value) {
            $item->tag('shared');

            return $value;
        };

        $pool->get('one', $tag('one'));
        $pool->get('two', $tag('two'));

        $container->get('test.cache.refresher')->enableRefresh();

        $this->assertSame('one/refreshed', $pool->get('one', $tag('one/refreshed')));

        $container->get('test.cache.refresher')->enableRefresh(false);

        // refreshing "one" must not have rotated the version of the tag "two" carries
        $this->assertSame('two', $pool->get('two', $tag('RECOMPUTED')));
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
            ->alias('test.cache.refresher', CachePoolRefresher::class)->public()
        ;
    }
}
