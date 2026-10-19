<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests;

require_once __DIR__.'/Fixtures/gc-function-mock.php';

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockBundle;
use Symfony\Component\Lock\LockFactory;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class LockBundleTest extends TestCase
{
    public static bool $collectCycles = true;

    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_lock_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testLockFactoriesAreRegistered()
    {
        $kernel = new TestLockKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $factory = $container->get('test.lock.factory');
        $this->assertInstanceOf(LockFactory::class, $factory);

        $lock = $factory->createLock('resource');
        $this->assertTrue($lock->acquire());
        $lock->release();

        $this->assertInstanceOf(LockFactory::class, $container->get('test.named.lock.factory'));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testShutdownClosesTheConnectionsOfStoresInAReferenceCycle()
    {
        $kernel = new ShutdownLockKernel($this->varDir);
        $kernel->boot();
        $holder = $kernel->getContainer()->get('test.factory_holder');

        $lock = $holder->factory->createLock('resource');
        $this->assertTrue($lock->acquire());
        $lock->release();

        $store = (new \ReflectionProperty($holder->factory, 'store'))->getValue($holder->factory);
        $conn = \WeakReference::create((new \ReflectionProperty($store, 'conn'))->getValue($store));
        $holder = \WeakReference::create($holder);
        unset($lock, $store);

        $kernel->getContainer()->get('services_resetter')->reset();
        $this->assertNotNull($conn->get());

        $gcEnabled = gc_enabled();
        gc_disable();
        self::$collectCycles = false;

        try {
            $kernel->shutdown();

            $this->assertNotNull($holder->get());
            $this->assertNull($conn->get());
        } finally {
            self::$collectCycles = true;
            gc_collect_cycles();

            if ($gcEnabled) {
                gc_enable();
            }
        }
    }
}

class TestLockKernel extends AbstractKernel
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
        yield new LockBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('lock', ['default' => 'in-memory', 'named' => 'in-memory']);
        $container->services()
            ->alias('test.lock.factory', 'lock.factory')->public()
            ->alias('test.named.lock.factory', 'lock.named.factory')->public()
        ;
    }
}

class ShutdownLockKernel extends AbstractKernel
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
        yield new LockBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('lock', ['default' => 'sqlite:'.$this->dir.'/lock.db']);
        $container->services()
            ->set('test.factory_holder', SelfReferencingFactoryHolder::class)
                ->args([service('lock.factory')])
                ->public()
        ;
    }
}

class SelfReferencingFactoryHolder
{
    public self $self;

    public function __construct(
        public LockFactory $factory,
    ) {
        $this->self = $this;
    }
}
