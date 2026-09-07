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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Semaphore\SemaphoreBundle;
use Symfony\Component\Semaphore\SemaphoreFactory;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

class SemaphoreBundleTest extends TestCase
{
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
