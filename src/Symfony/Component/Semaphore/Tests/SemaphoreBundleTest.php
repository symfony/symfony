<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests;

require_once __DIR__.'/Fixtures/gc-function-mock.php';

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Semaphore\SemaphoreBundle;
use Symfony\Component\Semaphore\SemaphoreFactory;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class SemaphoreBundleTest extends TestCase
{
    public static bool $collectCycles = true;

    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_semaphore_bundle_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testSemaphoreFactoriesAreRegistered()
    {
        $kernel = new TestSemaphoreKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $factory = $container->get('test.semaphore.factory');
        $this->assertInstanceOf(SemaphoreFactory::class, $factory);

        $semaphore = $factory->createSemaphore('resource', 2);
        $this->assertTrue($semaphore->acquire());
        $semaphore->release();
    }

    #[RequiresPhpExtension('redis')]
    public function testShutdownClosesTheConnectionsOfStoresInAReferenceCycle()
    {
        if (!class_exists(AbstractAdapter::class)) {
            $this->markTestSkipped('The Cache component is required.');
        }

        try {
            (new \Redis())->connect(...explode(':', getenv('REDIS_HOST')));
        } catch (\Exception $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $kernel = new ShutdownSemaphoreKernel($this->varDir);
        $kernel->boot();
        $holder = $kernel->getContainer()->get('test.factory_holder');

        $semaphore = $holder->factory->createSemaphore('resource', 2);
        $this->assertTrue($semaphore->acquire());
        $semaphore->release();

        $store = (new \ReflectionProperty($holder->factory, 'store'))->getValue($holder->factory);
        $redis = \WeakReference::create((new \ReflectionProperty($store, 'redis'))->getValue($store)->initializeLazyObject());
        $holder = \WeakReference::create($holder);
        unset($semaphore, $store);

        $kernel->getContainer()->get('services_resetter')->reset();
        $this->assertNotNull($redis->get());

        $gcEnabled = gc_enabled();
        gc_disable();
        self::$collectCycles = false;

        try {
            $kernel->shutdown();

            $this->assertNotNull($holder->get());
            $this->assertNull($redis->get());
        } finally {
            self::$collectCycles = true;
            gc_collect_cycles();

            if ($gcEnabled) {
                gc_enable();
            }
        }
    }
}

class TestSemaphoreKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new SemaphoreBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('semaphore', ['default' => 'lock://']);
        $container->services()
            ->set('lock.default.factory', LockFactory::class)->args([inline_service(InMemoryStore::class)])
            ->alias('test.semaphore.factory', 'semaphore.factory')->public()
        ;
    }
}

class ShutdownSemaphoreKernel extends AbstractKernel
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
        yield new SemaphoreBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('semaphore', ['default' => 'redis://'.getenv('REDIS_HOST')]);
        $container->services()
            ->set('test.factory_holder', SelfReferencingFactoryHolder::class)
                ->args([service('semaphore.factory')])
                ->public()
        ;
    }
}

class SelfReferencingFactoryHolder
{
    public self $self;

    public function __construct(
        public SemaphoreFactory $factory,
    ) {
        $this->self = $this;
    }
}
