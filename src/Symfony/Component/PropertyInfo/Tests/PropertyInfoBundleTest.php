<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\PropertyInfoBundle;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\TypeInfo\Type;

class PropertyInfoBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_property_info_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheExtractorIsRegisteredWithoutAnyCachePool()
    {
        $kernel = new TestPropertyInfoKernel('test', false, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertFalse($container->has('cache.property_info'));

        $propertyInfo = $container->get('test.property_info');
        $this->assertInstanceOf(PropertyInfoExtractor::class, $propertyInfo);
        $this->assertEquals(Type::string(), $propertyInfo->getType(PropertyInfoBundleSubject::class, 'foo'));
    }

    public function testTheCachePoolIsRegisteredAlongsideTheSystemPool()
    {
        $kernel = new TestPropertyInfoKernel('cached', false, $this->varDir, true);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertTrue($container->has('test.cache'));

        $propertyInfo = $container->get('test.property_info');
        $this->assertInstanceOf(PropertyInfoCacheExtractor::class, $propertyInfo);
        $this->assertEquals(Type::string(), $propertyInfo->getType(PropertyInfoBundleSubject::class, 'foo'));
    }

    public function testTaggedExtractorsAreCollectedThroughAutoconfiguration()
    {
        $kernel = new TestPropertyInfoKernel('test', false, $this->varDir);
        $kernel->boot();

        $this->assertSame(['foo', 'bar'], $kernel->getContainer()->get('test.property_info')->getProperties(PropertyInfoBundleSubject::class));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = new ContainerBuilder();
        new PropertyInfoBundle()->getContainerExtension()->load([['enabled' => false]], $container);

        $this->assertFalse($container->hasDefinition('property_info'));
        $this->assertFalse($container->hasDefinition('property_info.reflection_extractor'));
        $this->assertFalse($container->hasDefinition('cache.property_info'));
    }
}

class PropertyInfoBundleSubject
{
    public string $foo = 'bar';
}

class PropertyInfoBundleListExtractor implements PropertyListExtractorInterface
{
    public function getProperties(string $class, array $context = []): ?array
    {
        return PropertyInfoBundleSubject::class === $class ? ['foo', 'bar'] : null;
    }
}

class TestPropertyInfoKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir, private bool $withCacheSystem = false)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new PropertyInfoBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $services = $container->services();
        $services
            ->set(PropertyInfoBundleListExtractor::class)->autoconfigure()
            ->alias('test.property_info', 'property_info')->public()
        ;

        if ($this->withCacheSystem) {
            $services
                ->set('cache.system', ArrayAdapter::class)
                ->alias('test.cache', 'cache.property_info')->public()
            ;
        }
    }
}
