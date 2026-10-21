<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\ObjectMapper\Metadata\CachePropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\PropertyReadability;
use Symfony\Component\ObjectMapper\Metadata\SharedClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\SharedPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\SharedPropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperBundle;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\CollectionSource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\CollectionSourceItem;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\CollectionTarget;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\NestedEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\NestedEntityResource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\ObjectMapped;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\ObjectToBeMapped;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\ParentEntity;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\ParentEntityResource;
use Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle\TransformCallable;
use Symfony\Component\ObjectMapper\Transform\MapCollection;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class ObjectMapperBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_object_mapper_bundle_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testObjectMapperIsRegistered()
    {
        $kernel = new TestObjectMapperKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $objectMapper = $container->get('test.object_mapper');
        $this->assertInstanceOf(ObjectMapper::class, $objectMapper);
        $this->assertSame($objectMapper, $container->get('test.object_mapper_interface'));
    }

    public function testPublicPropertiesAreReadWithoutThePropertyAccessorWhenItUsesThePropertyInfoExtractors()
    {
        $kernel = new TestObjectMapperKernel('test', true, $this->varDir);
        $kernel->boot();
        $propertyMetadataFactory = (new \ReflectionProperty(ObjectMapper::class, 'propertyMetadataFactory'))->getValue($kernel->getContainer()->get('test.object_mapper'));

        $this->assertSame(PropertyReadability::INITIALIZED, $propertyMetadataFactory->create(new ObjectToBeMapped(), new ObjectMapped(), 'a')['readability']);
    }

    public function testTransformCallableIsAutoconfigured()
    {
        $kernel = new TestObjectMapperKernel('test', true, $this->varDir);
        $kernel->boot();

        $mapped = $kernel->getContainer()->get('test.object_mapper')->map(new ObjectToBeMapped());

        $this->assertInstanceOf(ObjectMapped::class, $mapped);
        $this->assertSame('transformed', $mapped->a);
    }

    public function testMapCollectionUsesContainerObjectMapper()
    {
        $kernel = new TestObjectMapperKernel('test', true, $this->varDir);
        $kernel->boot();

        $source = new CollectionSource([
            new CollectionSourceItem('foo'),
            new CollectionSourceItem('bar'),
        ]);

        /** @var CollectionTarget $mapped */
        $mapped = $kernel->getContainer()->get('test.object_mapper')->map($source);

        $this->assertCount(2, $mapped->items);
        $this->assertSame('foo', $mapped->items[0]->getName());
        $this->assertSame('bar', $mapped->items[1]->getName());
    }

    public function testMapNestedObjectWhoseClassDeclaresNoMapping()
    {
        $kernel = new TestObjectMapperKernel('test', true, $this->varDir);
        $kernel->boot();

        /** @var ParentEntityResource $mapped */
        $mapped = $kernel->getContainer()->get('test.object_mapper')->map(new ParentEntity('Laptop', new NestedEntity('Electronics')), ParentEntityResource::class);

        $this->assertSame('Laptop', $mapped->name);
        $this->assertInstanceOf(NestedEntityResource::class, $mapped->nested);
        $this->assertSame('Electronics', $mapped->nested->name);
    }

    public function testMapNestedObjectWhenNoClassMapIsBuilt()
    {
        $kernel = new TestObjectMapperWithoutPropertyAccessorKernel('test', true, $this->varDir);
        $kernel->boot();

        /** @var ParentEntityResource $mapped */
        $mapped = $kernel->getContainer()->get('test.object_mapper')->map(new ParentEntity('Laptop', new NestedEntity('Electronics')), ParentEntityResource::class);

        $this->assertSame('Laptop', $mapped->name);
        $this->assertInstanceOf(NestedEntityResource::class, $mapped->nested);
        $this->assertSame('Electronics', $mapped->nested->name);
    }

    public function testObjectMapperWorksWithoutPropertyAccessor()
    {
        $kernel = new TestObjectMapperWithoutPropertyAccessorKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertFalse($container->has('property_accessor'));

        $mapped = $container->get('test.object_mapper')->map(new ObjectToBeMapped());

        $this->assertInstanceOf(ObjectMapped::class, $mapped);
        $this->assertSame('transformed', $mapped->a);
    }

    public function testTheObjectMapperMapsThroughTheMetadataCacheWhenNotInDebug()
    {
        $kernel = new TestObjectMapperWithSystemCacheKernel('test', false, $this->varDir);
        $kernel->boot();
        $objectMapper = $kernel->getContainer()->get('test.object_mapper');

        $mapped = $objectMapper->map(new ObjectToBeMapped());

        $this->assertInstanceOf(ObjectMapped::class, $mapped);
        $this->assertSame('transformed', $mapped->a);
        $this->assertInstanceOf(SharedPropertyMetadataFactory::class, $factory = (new \ReflectionProperty(ObjectMapper::class, 'propertyMetadataFactory'))->getValue($objectMapper));
        $this->assertInstanceOf(CachePropertyMetadataFactory::class, (new \ReflectionProperty(SharedPropertyMetadataFactory::class, 'decorated'))->getValue($factory));
    }

    public function testTheMetadataCacheAndItsWarmerAreRegisteredWhenNotInDebug()
    {
        $container = $this->load(debug: false);

        $this->assertTrue($container->hasDefinition('object_mapper.metadata.cache_class_metadata_factory'));
        $this->assertTrue($container->hasDefinition('object_mapper.metadata.cache_property_name_collection_factory'));
        $this->assertTrue($container->hasDefinition('object_mapper.metadata.cache_property_metadata_factory'));
        $this->assertTrue($container->hasDefinition('object_mapper.metadata.cache_warmer'));
        $this->assertTrue($container->hasDefinition('cache.object_mapper'));
    }

    public function testTheMetadataIsSharedByTheContainersOfTheSameBuildOutsideDebugMode()
    {
        $container = $this->load(debug: false);
        new DecoratorServicePass()->process($container);

        foreach ([
            'object_mapper.class_metadata_factory' => SharedClassMetadataFactory::class,
            'object_mapper.property_name_collection_factory' => SharedPropertyNameCollectionFactory::class,
            'object_mapper.property_metadata_factory' => SharedPropertyMetadataFactory::class,
        ] as $id => $class) {
            $definition = $container->findDefinition($id);
            $this->assertSame($class, $definition->getClass());
            $this->assertEquals(new Parameter('container.build_id'), $definition->getArgument(1));
        }

        $container = $this->load(debug: true);
        $this->assertFalse($container->hasDefinition('object_mapper.metadata.shared_class_metadata_factory'));
        $this->assertFalse($container->hasDefinition('object_mapper.metadata.shared_property_name_collection_factory'));
        $this->assertFalse($container->hasDefinition('object_mapper.metadata.shared_property_metadata_factory'));
    }

    public function testTheMetadataCacheAndItsWarmerAreRemovedInDebug()
    {
        $container = $this->load(debug: true);

        $this->assertFalse($container->hasDefinition('object_mapper.metadata.cache_class_metadata_factory'));
        $this->assertFalse($container->hasDefinition('object_mapper.metadata.cache_property_name_collection_factory'));
        $this->assertFalse($container->hasDefinition('object_mapper.metadata.cache_property_metadata_factory'));
        $this->assertFalse($container->hasDefinition('object_mapper.metadata.cache_warmer'));
        $this->assertTrue($container->hasDefinition('object_mapper.property_metadata_factory'));
    }

    public function testTheMetadataCacheAndItsWarmerAreRemovedWithoutTheSystemCachePool()
    {
        $container = $this->load(debug: false, cache: false);

        $this->assertFalse($container->hasDefinition('object_mapper.metadata.cache_property_metadata_factory'));
        $this->assertFalse($container->hasDefinition('object_mapper.metadata.cache_warmer'));
        $this->assertFalse($container->hasDefinition('cache.object_mapper'));
        $this->assertTrue($container->hasDefinition('object_mapper.property_metadata_factory'));
    }

    private function load(bool $debug, bool $cache = true): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => $debug,
            'kernel.build_dir' => sys_get_temp_dir(),
            'kernel.project_dir' => '/app',
            'kernel.bundles_metadata' => [],
            'kernel.container_class' => 'TestContainer',
        ]));

        if ($cache) {
            $container->register('cache.system', \stdClass::class);
        }

        $container->registerExtension(new ObjectMapperBundle()->getContainerExtension());
        $container->loadFromExtension('object_mapper', []);

        new MergeExtensionConfigurationPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        return $container;
    }
}

class TestObjectMapperKernel extends AbstractKernel
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
        yield new ObjectMapperBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $services = $container->services();
        $services->set('property_info.reflection_extractor', ReflectionExtractor::class);
        $services->alias(PropertyReadInfoExtractorInterface::class, 'property_info.reflection_extractor');
        $services->alias(PropertyWriteInfoExtractorInterface::class, 'property_info.reflection_extractor');
        $services->set('property_accessor', PropertyAccessor::class)->args([PropertyAccessor::MAGIC_GET | PropertyAccessor::MAGIC_SET, PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH, null, service(PropertyReadInfoExtractorInterface::class), service(PropertyWriteInfoExtractorInterface::class)]);
        $services->set(TransformCallable::class)->autoconfigure();
        $services->set(MapCollection::class)->autoconfigure();
        $services->set(ParentEntityResource::class)->autoconfigure();
        $services->set(NestedEntityResource::class)->autoconfigure();
        $services->alias('test.object_mapper', 'object_mapper')->public();
        $services->alias('test.object_mapper_interface', ObjectMapperInterface::class)->public();
    }
}

class TestObjectMapperWithoutPropertyAccessorKernel extends AbstractKernel
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
        yield new ObjectMapperBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $services = $container->services();
        $services->set(TransformCallable::class)->autoconfigure();
        $services->alias('test.object_mapper', 'object_mapper')->public();
    }
}

class TestObjectMapperWithSystemCacheKernel extends AbstractKernel
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
        yield new ObjectMapperBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $services = $container->services();
        $services->set('cache.system', ArrayAdapter::class);
        $services->set(TransformCallable::class)->autoconfigure();
        $services->alias('test.object_mapper', 'object_mapper')->public();
    }
}
