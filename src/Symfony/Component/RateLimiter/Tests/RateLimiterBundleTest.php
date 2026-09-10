<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass as ContainerRemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterBundle;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimiterBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_rate_limiter_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testLimitersAreRegistered()
    {
        $kernel = new TestRateLimiterKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $factory = $container->get('test.limiter');
        $this->assertInstanceOf(RateLimiterFactory::class, $factory);

        $limiter = $factory->create('key');
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertFalse($limiter->consume(3)->isAccepted());

        $this->assertInstanceOf(CompoundRateLimiterFactory::class, $container->get('test.compound_limiter'));
        $this->assertInstanceOf(RateLimiterBuilder::class, $container->get('test.builder'));
        $this->assertInstanceOf(ArrayAdapter::class, $container->get('test.cache'));
    }

    public function testTheDefaultLockFactoryIsWiredWhenItExists()
    {
        $kernel = new TestRateLimiterKernel('lock', true, $this->varDir, true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $limiter = $container->get('test.limiter')->create('key');
        $this->assertTrue($limiter->consume()->isAccepted());

        $this->assertInstanceOf(RateLimiterBuilder::class, $container->get('test.builder'));
    }

    public function testTheCachePoolGoesWithTheAppPool()
    {
        $container = new ContainerBuilder();
        new RateLimiterBundle()->getContainerExtension()->load([[]], $container);

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);
        $this->assertFalse($container->hasDefinition('cache.rate_limiter'), 'dropped without "cache.app"');

        $container = new ContainerBuilder();
        $container->register('cache.app');
        new RateLimiterBundle()->getContainerExtension()->load([[]], $container);

        new ContainerRemoveMissingDependenciesPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);
        $this->assertTrue($container->hasDefinition('cache.rate_limiter'));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new RateLimiterBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('limiter'));
        $this->assertFalse($container->hasDefinition('limiter_builder'));
        $this->assertFalse($container->hasDefinition('cache.rate_limiter'));
        $this->assertFalse($container->hasDefinition('rate_limiter.attribute_listener'));
    }
}

class TestRateLimiterKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private bool $withLock = false)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new RateLimiterBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('rate_limiter', [
            'limiters' => [
                'main' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
                'secondary' => ['policy' => 'sliding_window', 'limit' => 5, 'interval' => '1 hour'],
                'both' => ['policy' => 'compound', 'limiters' => ['main', 'secondary']],
            ],
        ]);

        $services = $container->services();
        $services
            ->set('cache.app', ArrayAdapter::class)
            ->alias('test.limiter', 'limiter.main')->public()
            ->alias('test.compound_limiter', 'limiter.both')->public()
            ->alias('test.builder', 'limiter_builder')->public()
            ->alias('test.cache', 'cache.rate_limiter')->public()
        ;

        if ($this->withLock) {
            $services
                ->set('lock.in_memory_store', InMemoryStore::class)
                ->set('lock.default.factory', LockFactory::class)
                    ->args([new Reference('lock.in_memory_store')])
                ->alias('lock.factory', 'lock.default.factory')
            ;
        }
    }
}
