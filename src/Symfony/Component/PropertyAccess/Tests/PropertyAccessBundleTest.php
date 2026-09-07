<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyAccess\PropertyAccessBundle;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class PropertyAccessBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_property_access_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testPropertyAccessorIsRegisteredWithoutAnyCachePool()
    {
        $kernel = new TestPropertyAccessKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertFalse($container->has('cache.property_access'));

        $accessor = $container->get('test.property_accessor');
        $this->assertInstanceOf(PropertyAccessor::class, $accessor);
        $this->assertSame('bar', $accessor->getValue(new PropertyAccessBundleSubject(), 'foo'));
    }

    public function testConfigurationIsApplied()
    {
        $kernel = new TestPropertyAccessKernel('configured', true, $this->varDir, ['throw_exception_on_invalid_property_path' => false]);
        $kernel->boot();

        $accessor = $kernel->getContainer()->get('test.property_accessor');
        $this->assertNull($accessor->getValue(new PropertyAccessBundleSubject(), 'unknown'));
    }
}

class PropertyAccessBundleSubject
{
    public string $foo = 'bar';
}

class TestPropertyAccessKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private array $config = [])
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new PropertyAccessBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('property_access', $this->config);
        $container->services()->alias('test.property_accessor', 'property_accessor')->public();
    }
}
