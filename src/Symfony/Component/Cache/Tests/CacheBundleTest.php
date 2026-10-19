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

require_once __DIR__.'/Fixtures/gc-function-mock.php';

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\CacheBundle;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class CacheBundleTest extends TestCase
{
    public static bool $collectCycles = true;

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

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testShutdownClosesTheConnectionsOfPoolsInAReferenceCycle()
    {
        $this->assertShutdownClosesTheConnection('test.pool_holder', static fn (TraceableAdapter $pool) => (new \ReflectionProperty(TraceableAdapter::class, 'pool'))->getValue($pool));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testShutdownClosesTheConnectionsOfChainedPools()
    {
        $this->assertShutdownClosesTheConnection('test.chain_holder', static function (TraceableAdapter $pool) {
            $chain = (new \ReflectionProperty(TraceableAdapter::class, 'pool'))->getValue($pool);

            return (new \ReflectionProperty(ChainAdapter::class, 'adapters'))->getValue($chain)[1];
        });
    }

    private function assertShutdownClosesTheConnection(string $holderId, \Closure $getPdoAdapter): void
    {
        $kernel = new ShutdownCacheKernel($this->varDir);
        $kernel->boot();
        $holder = $kernel->getContainer()->get($holderId);
        $holder->pool->save($holder->pool->getItem('foo')->set('bar'));
        $this->assertInstanceOf(TraceableAdapter::class, $holder->pool);
        $pdoAdapter = $getPdoAdapter($holder->pool);
        $this->assertInstanceOf(PdoAdapter::class, $pdoAdapter);
        $connection = \WeakReference::create((new \ReflectionMethod($pdoAdapter, 'getConnection'))->invoke($pdoAdapter));
        $holder = \WeakReference::create($holder);
        unset($pdoAdapter);

        $gcEnabled = gc_enabled();
        gc_disable();
        self::$collectCycles = false;

        try {
            $kernel->shutdown();

            $this->assertNotNull($holder->get());
            $this->assertNull($connection->get());
        } finally {
            self::$collectCycles = true;
            gc_collect_cycles();

            if ($gcEnabled) {
                gc_enable();
            }
        }
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

class ShutdownCacheKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(private string $dir)
    {
        parent::__construct('shutdown', true);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new ServicesBundle();
        yield new CacheBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('cache', [
            'pools' => [
                'cache.pdo' => ['adapters' => 'cache.adapter.pdo', 'provider' => 'sqlite:'.$this->dir.'/var/cache.db'],
                'cache.chain' => ['adapters' => ['cache.adapter.array', 'sqlite:'.$this->dir.'/var/chain.db' => 'cache.adapter.pdo']],
            ],
        ]);

        $container->services()
            ->set('data_collector.cache', CacheDataCollector::class)
            ->set('test.pool_holder', SelfReferencingPoolHolder::class)
                ->args([service('cache.pdo')])
                ->public()
            ->set('test.chain_holder', SelfReferencingPoolHolder::class)
                ->args([service('cache.chain')])
                ->public()
        ;
    }
}

class SelfReferencingPoolHolder
{
    public self $self;

    public function __construct(
        public CacheItemPoolInterface $pool,
    ) {
        $this->self = $this;
    }
}
