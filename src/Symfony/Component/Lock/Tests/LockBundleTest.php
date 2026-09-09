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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockBundle;
use Symfony\Component\Lock\LockFactory;

class LockBundleTest extends TestCase
{
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
