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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class DoctrineExtensionTest extends TestCase
{
    /**
     * @var \Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension
     */
    private $extension;

    protected function setUp()
    {
        parent::setUp();

        $this->extension = $this
            ->getMockBuilder('Symfony\Bridge\Doctrine\DependencyInjection\AbstractDoctrineExtension')
            ->setMethods(array(
                'getMappingResourceConfigDirectory',
                'getObjectManagerElementName',
                'getMappingObjectDefaultName',
                'getMappingResourceExtension',
                'load',
            ))
            ->getMock()
        ;

        $this->extension->expects($this->any())
            ->method('getObjectManagerElementName')
            ->will($this->returnCallback(function ($name) {
                return 'doctrine.orm.'.$name;
            }));
    }

    /**
     * @expectedException \LogicException
     */
    public function testFixManagersAutoMappingsWithTwoAutomappings()
    {
        $emConfigs = array(
            'em1' => array(
                'auto_mapping' => true,
            ),
            'em2' => array(
                'auto_mapping' => true,
            ),
        );

        $bundles = array(
            'FristBundle' => 'My\FristBundle',
            'SecondBundle' => 'My\SecondBundle',
        );

        $reflection = new \ReflectionClass(get_class($this->extension));
        $method = $reflection->getMethod('fixManagersAutoMappings');
        $method->setAccessible(true);

        $method->invoke($this->extension, $emConfigs, $bundles);
    }

    public function getAutomappingData()
    {
        return array(
            array(
                array( // no auto mapping on em1
                    'auto_mapping' => false,
                ),
                array( // no auto mapping on em2
                    'auto_mapping' => false,
                ),
                array(),
                array(),
            ),
            array(
                array( // no auto mapping on em1
                    'auto_mapping' => false,
                ),
                array( // auto mapping enabled on em2
                    'auto_mapping' => true,
                ),
                array(),
                array(
                    'mappings' => array(
                        'FristBundle' => array(
                            'mapping' => true,
                            'is_bundle' => true,
                        ),
                        'SecondBundle' => array(
                            'mapping' => true,
                            'is_bundle' => true,
                        ),
                    ),
                ),
            ),
            array(
                array( // no auto mapping on em1, but it defines SecondBundle as own
                    'auto_mapping' => false,
                    'mappings' => array(
                        'SecondBundle' => array(
                            'mapping' => true,
                            'is_bundle' => true,
                        ),
                    ),
                ),
                array( // auto mapping enabled on em2
                    'auto_mapping' => true,
                ),
                array(
                    'mappings' => array(
                        'SecondBundle' => array(
                            'mapping' => true,
                            'is_bundle' => true,
                        ),
                    ),
                ),
                array(
                    'mappings' => array(
                        'FristBundle' => array(
                            'mapping' => true,
                            'is_bundle' => true,
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @dataProvider getAutomappingData
     */
    public function testFixManagersAutoMappings(array $originalEm1, array $originalEm2, array $expectedEm1, array $expectedEm2)
    {
        $emConfigs = array(
            'em1' => $originalEm1,
            'em2' => $originalEm2,
        );

        $bundles = array(
            'FristBundle' => 'My\FristBundle',
            'SecondBundle' => 'My\SecondBundle',
        );

        $reflection = new \ReflectionClass(get_class($this->extension));
        $method = $reflection->getMethod('fixManagersAutoMappings');
        $method->setAccessible(true);

        $newEmConfigs = $method->invoke($this->extension, $emConfigs, $bundles);

        $this->assertEquals($newEmConfigs['em1'], array_merge(array(
            'auto_mapping' => false,
        ), $expectedEm1));
        $this->assertEquals($newEmConfigs['em2'], array_merge(array(
            'auto_mapping' => false,
        ), $expectedEm2));
    }

    public function providerBasicDrivers()
    {
        return array(
            array('doctrine.orm.cache.apc.class',       array('type' => 'apc')),
            array('doctrine.orm.cache.apcu.class',      array('type' => 'apcu')),
            array('doctrine.orm.cache.array.class',     array('type' => 'array')),
            array('doctrine.orm.cache.xcache.class',    array('type' => 'xcache')),
            array('doctrine.orm.cache.wincache.class',  array('type' => 'wincache')),
            array('doctrine.orm.cache.zenddata.class',  array('type' => 'zenddata')),
            array('doctrine.orm.cache.redis.class',     array('type' => 'redis'),     array('setRedis')),
            array('doctrine.orm.cache.memcached.class', array('type' => 'memcached'), array('setMemcached')),
        );
    }

    /**
     * @dataProvider providerBasicDrivers
     */
    public function testLoadBasicCacheDriver(string $class, array $config, array $expectedCalls = array())
    {
        $container = $this->createContainer();
        $cacheName = 'metadata_cache';
        $objectManager = array(
            'name' => 'default',
            'metadata_cache_driver' => $config,
        );

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
        $objectManager = array(
            'name' => 'default',
            'metadata_cache_driver' => array(
                'type' => 'service',
                'id' => 'service_driver',
            ),
        );

        $container->setDefinition('service_driver', $definition);

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);

        $this->assertTrue($container->hasAlias('doctrine.orm.default_metadata_cache'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage "unrecognized_type" is an unrecognized Doctrine cache driver.
     */
    public function testUnrecognizedCacheDriverException()
    {
        $cacheName = 'metadata_cache';
        $container = $this->createContainer();
        $objectManager = array(
            'name' => 'default',
            'metadata_cache_driver' => array(
                'type' => 'unrecognized_type',
            ),
        );

        $this->invokeLoadCacheDriver($objectManager, $container, $cacheName);
    }

    protected function invokeLoadCacheDriver(array $objectManager, ContainerBuilder $container, $cacheName)
    {
        $method = new \ReflectionMethod($this->extension, 'loadObjectManagerCacheDriver');

        $method->setAccessible(true);

        $method->invokeArgs($this->extension, array($objectManager, $container, $cacheName));
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerBuilder
     */
    protected function createContainer(array $data = array())
    {
        return new ContainerBuilder(new ParameterBag(array_merge(array(
            'kernel.bundles' => array('FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'),
            'kernel.cache_dir' => __DIR__,
            'kernel.debug' => false,
            'kernel.environment' => 'test',
            'kernel.name' => 'kernel',
            'kernel.root_dir' => __DIR__,
        ), $data)));
    }
}
