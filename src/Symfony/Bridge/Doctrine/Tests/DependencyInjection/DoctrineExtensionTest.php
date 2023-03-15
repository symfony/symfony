<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class DoctrineExtensionTest extends TestCase
{
    /**
     * @var AbstractDoctrineExtension
     */
    private $extension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = $this
            ->getMockBuilder(AbstractDoctrineExtension::class)
            ->onlyMethods([
                'getMappingResourceConfigDirectory',
                'getObjectManagerElementName',
                'getMappingObjectDefaultName',
                'getMappingResourceExtension',
                'getMetadataDriverClass',
                'load',
            ])
            ->getMock()
        ;

        $this->extension->expects($this->any())
            ->method('getObjectManagerElementName')
            ->willReturnCallback(fn ($name) => 'doctrine.orm.'.$name);

        $this->extension
            ->method('getMappingObjectDefaultName')
            ->willReturn('Entity');

        $this->extension
            ->method('getMappingResourceExtension')
            ->willReturn('orm');
    }

    public function testFixManagersAutoMappingsWithTwoAutomappings()
    {
        $this->expectException(\LogicException::class);
        $emConfigs = [
            'em1' => [
                'auto_mapping' => true,
            ],
            'em2' => [
                'auto_mapping' => true,
            ],
        ];

        $bundles = [
            'FirstBundle' => 'My\FirstBundle',
            'SecondBundle' => 'My\SecondBundle',
        ];

        $reflection = new \ReflectionClass($this->extension);
        $method = $reflection->getMethod('fixManagersAutoMappings');

        $method->invoke($this->extension, $emConfigs, $bundles);
    }

    public static function getAutomappingData()
    {
        return [
            [
                [ // no auto mapping on em1
                    'auto_mapping' => false,
                ],
                [ // no auto mapping on em2
                    'auto_mapping' => false,
                ],
                [],
                [],
            ],
            [
                [ // no auto mapping on em1
                    'auto_mapping' => false,
                ],
                [ // auto mapping enabled on em2
                    'auto_mapping' => true,
                ],
                [],
                [
                    'mappings' => [
                        'FirstBundle' => [
                            'mapping' => true,
                            'is_bundle' => true,
                        ],
                        'SecondBundle' => [
                            'mapping' => true,
                            'is_bundle' => true,
                        ],
                    ],
                ],
            ],
            [
                [ // no auto mapping on em1, but it defines SecondBundle as own
                    'auto_mapping' => false,
                    'mappings' => [
                        'SecondBundle' => [
                            'mapping' => true,
                            'is_bundle' => true,
                        ],
                    ],
                ],
                [ // auto mapping enabled on em2
                    'auto_mapping' => true,
                ],
                [
                    'mappings' => [
                        'SecondBundle' => [
                            'mapping' => true,
                            'is_bundle' => true,
                        ],
                    ],
                ],
                [
                    'mappings' => [
                        'FirstBundle' => [
                            'mapping' => true,
                            'is_bundle' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider getAutomappingData
     */
    public function testFixManagersAutoMappings(array $originalEm1, array $originalEm2, array $expectedEm1, array $expectedEm2)
    {
        $emConfigs = [
            'em1' => $originalEm1,
            'em2' => $originalEm2,
        ];

        $bundles = [
            'FirstBundle' => 'My\FirstBundle',
            'SecondBundle' => 'My\SecondBundle',
        ];

        $reflection = new \ReflectionClass($this->extension);
        $method = $reflection->getMethod('fixManagersAutoMappings');

        $newEmConfigs = $method->invoke($this->extension, $emConfigs, $bundles);

        $this->assertEquals($newEmConfigs['em1'], array_merge([
            'auto_mapping' => false,
        ], $expectedEm1));
        $this->assertEquals($newEmConfigs['em2'], array_merge([
            'auto_mapping' => false,
        ], $expectedEm2));
    }

    public function testMappingTypeDetection()
    {
        $container = $this->createContainer();

        $reflection = new \ReflectionClass($this->extension);
        $method = $reflection->getMethod('detectMappingType');

        // The ordinary fixtures contain annotation
        $mappingType = $method->invoke($this->extension, __DIR__.'/../Fixtures', $container);
        $this->assertSame($mappingType, 'annotation');

        // In the attribute folder, attributes are used
        $mappingType = $method->invoke($this->extension, __DIR__.'/../Fixtures/Attribute', $container);
        $this->assertSame($mappingType, 'attribute');
    }

    public static function providerBasicDrivers()
    {
        return [
            ['doctrine.orm.cache.apc.class',       ['type' => 'apc']],
            ['doctrine.orm.cache.apcu.class',      ['type' => 'apcu']],
            ['doctrine.orm.cache.array.class',     ['type' => 'array']],
            ['doctrine.orm.cache.xcache.class',    ['type' => 'xcache']],
            ['doctrine.orm.cache.wincache.class',  ['type' => 'wincache']],
            ['doctrine.orm.cache.zenddata.class',  ['type' => 'zenddata']],
            ['doctrine.orm.cache.redis.class',     ['type' => 'redis'],     ['setRedis']],
            ['doctrine.orm.cache.memcached.class', ['type' => 'memcached'], ['setMemcached']],
        ];
    }

    /**
     * @dataProvider providerBasicDrivers
     */
    public function testLoadBasicCacheDriver(string $class, array $config, array $expectedCalls = [])
    {
        $container = $this->createContainer();
        $cacheName = 'metadata_cache';
        $objectManager = [
            'name' => 'default',
            'metadata_cache_driver' => $config,
        ];

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);

        $this->assertTrue($container->hasDefinition('doctrine.orm.default_metadata_cache'));

        $definition = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $defCalls = $definition->getMethodCalls();
        $expectedCalls[] = 'setNamespace';
        $actualCalls = array_column($defCalls, 0);

        $this->assertFalse($definition->isPublic());
        $this->assertEquals("%$class%", $definition->getClass());

        foreach (array_unique($expectedCalls) as $call) {
            $this->assertContains($call, $actualCalls);
        }
    }

    public function testServiceCacheDriver()
    {
        $cacheName = 'metadata_cache';
        $container = $this->createContainer();
        $definition = new Definition('%doctrine.orm.cache.apc.class%');
        $objectManager = [
            'name' => 'default',
            'metadata_cache_driver' => [
                'type' => 'service',
                'id' => 'service_driver',
            ],
        ];

        $container->setDefinition('service_driver', $definition);

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);

        $this->assertTrue($container->hasAlias('doctrine.orm.default_metadata_cache'));
    }

    public function testUnrecognizedCacheDriverException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"unrecognized_type" is an unrecognized Doctrine cache driver.');
        $cacheName = 'metadata_cache';
        $container = $this->createContainer();
        $objectManager = [
            'name' => 'default',
            'metadata_cache_driver' => [
                'type' => 'unrecognized_type',
            ],
        ];

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);
    }

    public static function providerBundles()
    {
        yield ['AnnotationsBundle', 'annotation', '/Entity'];
        yield ['AnnotationsOneLineBundle', 'annotation', '/Entity'];
        yield ['FullEmbeddableAnnotationsBundle', 'annotation', '/Entity'];
        yield ['AttributesBundle', 'attribute', '/Entity'];
        yield ['FullEmbeddableAttributesBundle', 'attribute', '/Entity'];
        yield ['XmlBundle', 'xml', '/Resources/config/doctrine'];
        yield ['PhpBundle', 'php', '/Resources/config/doctrine'];
        yield ['YamlBundle', 'yml', '/Resources/config/doctrine'];

        yield ['SrcXmlBundle', 'xml', '/Resources/config/doctrine'];

        yield ['NewAnnotationsBundle', 'annotation', \DIRECTORY_SEPARATOR.'src/Entity'];
        yield ['NewXmlBundle', 'xml', '/config/doctrine'];
    }

    /**
     * @dataProvider providerBundles
     */
    public function testBundleAutoMapping(string $bundle, string $expectedType, string $dirSuffix)
    {
        $bundleDir = __DIR__.'/../Fixtures/Bundles/'.$bundle;
        $bundleClassName = 'Fixtures\\Bundles\\'.$bundle.'\\'.$bundle;

        if (is_dir($bundleDir.'/src')) {
            require_once $bundleDir.'/src/'.$bundle.'.php';
        } else {
            require_once $bundleDir.'/'.$bundle.'.php';
        }

        /** @var BundleInterface $bundleClass */
        $bundleClass = new $bundleClassName();

        $mappingConfig = [
            'dir' => false,
            'type' => false,
            'prefix' => false,
            'mapping' => true,
            'is_bundle' => true,
        ];

        $this->extension
            ->method('getMappingResourceConfigDirectory')
            ->willReturnCallback(function ($bundleDir) {
                if (null !== $bundleDir && is_dir($bundleDir.'/config/doctrine')) {
                    return 'config/doctrine';
                }

                return 'Resources/config/doctrine';
            });

        $container = $this->createContainer([], [$bundle => $bundleClassName]);

        $reflection = new \ReflectionClass($this->extension);
        $method = $reflection->getMethod('getMappingDriverBundleConfigDefaults');

        $this->assertSame(
            [
                'dir' => $bundleClass->getPath().$dirSuffix,
                'type' => $expectedType,
                'prefix' => $bundleClass->getNamespace().'\\Entity',
                'mapping' => true,
                'is_bundle' => true,
            ],
            $method->invoke($this->extension, $mappingConfig, new \ReflectionClass($bundleClass), $container, $bundleClass->getPath())
        );
    }

    protected function invokeLoadCacheDriver(array $objectManager, ContainerBuilder $container, $cacheName)
    {
        $method = new \ReflectionMethod($this->extension, 'loadObjectManagerCacheDriver');

        $method->invokeArgs($this->extension, [$objectManager, $container, $cacheName]);
    }

    protected function createContainer(array $data = [], array $extraBundles = []): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag(array_merge([
            'kernel.bundles' => array_merge(['FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'], $extraBundles),
            'kernel.cache_dir' => __DIR__,
            'kernel.build_dir' => __DIR__,
            'kernel.container_class' => 'kernel',
            'kernel.project_dir' => __DIR__,
        ], $data)));
    }
}
