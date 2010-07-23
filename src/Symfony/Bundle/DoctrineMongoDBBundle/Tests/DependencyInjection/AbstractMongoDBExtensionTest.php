<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Tests\DependencyInjection;

use Symfony\Bundle\DoctrineMongoDBBundle\Tests\TestCase;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\MongoDBExtension;
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Components\DependencyInjection\Loader\YamlFileLoader;

abstract class AbstractMongoDBExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testDependencyInjectionConfigurationDefaults()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader();

        $loader->mongodbLoad(array(), $container);

        $this->assertEquals('Doctrine\ODM\MongoDB\Mongo', $container->getParameter('doctrine.odm.mongodb.connection_class'));
        $this->assertEquals('Doctrine\ODM\MongoDB\Configuration', $container->getParameter('doctrine.odm.mongodb.configuration_class'));
        $this->assertEquals('Doctrine\ODM\MongoDB\DocumentManager', $container->getParameter('doctrine.odm.mongodb.document_manager_class'));
        $this->assertEquals('Proxies', $container->getParameter('doctrine.odm.mongodb.proxy_namespace'));
        $this->assertEquals(false, $container->getParameter('doctrine.odm.mongodb.auto_generate_proxy_classes'));
        $this->assertEquals('Doctrine\Common\Cache\ArrayCache', $container->getParameter('doctrine.odm.mongodb.cache.array_class'));
        $this->assertEquals('Doctrine\Common\Cache\ApcCache', $container->getParameter('doctrine.odm.mongodb.cache.apc_class'));
        $this->assertEquals('Doctrine\Common\Cache\MemcacheCache', $container->getParameter('doctrine.odm.mongodb.cache.memcache_class'));
        $this->assertEquals('localhost', $container->getParameter('doctrine.odm.mongodb.cache.memcache_host'));
        $this->assertEquals('11211', $container->getParameter('doctrine.odm.mongodb.cache.memcache_port'));
        $this->assertEquals('Memcache', $container->getParameter('doctrine.odm.mongodb.cache.memcache_instance_class'));
        $this->assertEquals('Doctrine\Common\Cache\XcacheCache', $container->getParameter('doctrine.odm.mongodb.cache.xcache_class'));
        $this->assertEquals('Doctrine\ODM\MongoDB\Mapping\Driver\DriverChain', $container->getParameter('doctrine.odm.mongodb.metadata.driver_chain_class'));
        $this->assertEquals('Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver', $container->getParameter('doctrine.odm.mongodb.metadata.annotation_class'));
        $this->assertEquals('Doctrine\Common\Annotations\AnnotationReader', $container->getParameter('doctrine.odm.mongodb.metadata.annotation_reader_class'));
        $this->assertEquals('Doctrine\ODM\MongoDB\Mapping\\', $container->getParameter('doctrine.odm.mongodb.metadata.annotation_default_namespace'));
        $this->assertEquals('Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver', $container->getParameter('doctrine.odm.mongodb.metadata.xml_class'));
        $this->assertEquals('Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver', $container->getParameter('doctrine.odm.mongodb.metadata.yml_class'));

        $config = array(
            'proxy_namespace' => 'MyProxies',
            'auto_generate_proxy_classes' => true,
        );
        $loader->mongodbLoad($config, $container);

        $this->assertEquals('MyProxies', $container->getParameter('doctrine.odm.mongodb.proxy_namespace'));
        $this->assertEquals(true, $container->getParameter('doctrine.odm.mongodb.auto_generate_proxy_classes'));

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array(null, array()), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.default_configuration', (string) $arguments[1]);
    }

    public function testSingleDocumentManagerConfiguration()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader();

        $config = array(
            'server' => 'mongodb://localhost:27017',
            'options' => array('connect' => true)
        );
        $loader->mongodbLoad($config, $container);

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true)), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.default_configuration', (string) $arguments[1]);
    }

    public function testLoadSimpleSingleConnection()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_simple_single_connection');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true)), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.default_configuration', (string) $arguments[1]);
    }

    public function testLoadSingleConnection()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_single_connection');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true)), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.default_configuration', (string) $arguments[1]);
    }

    public function testLoadMultipleConnections()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_multiple_connections');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.odm.mongodb.conn1_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true)), $definition->getArguments());

        $this->assertEquals('doctrine.odm.mongodb.dm2_document_manager', $container->getAlias('doctrine.odm.mongodb.document_manager'));

        $definition = $container->getDefinition('doctrine.odm.mongodb.dm1_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.conn1_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.dm1_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.odm.mongodb.conn2_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true)), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.dm2_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.conn2_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Components\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.dm2_configuration', (string) $arguments[1]);
    }

    public function testBundleDocumentAliases()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader();

        $loader->mongodbLoad(array(), $container);

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_configuration');
        $calls = $definition->getMethodCalls();
        $this->assertTrue(isset($calls[0][1][0]['YamlBundle']));
        $this->assertEquals('Fixtures\Bundles\YamlBundle\Document', $calls[0][1][0]['YamlBundle']);
    }

    public function testYamlBundleMappingDetection()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader('YamlBundle');

        $loader->mongodbLoad(array(), $container);

        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/YamlBundle/Resources/config/doctrine/metadata'), $container->getParameter('doctrine.odm.mongodb.mapping_dirs'));
        $this->assertEquals('%doctrine.odm.mongodb.mapping_dirs%', $container->getParameter('doctrine.odm.mongodb.xml_mapping_dirs'));
        $this->assertEquals('%doctrine.odm.mongodb.mapping_dirs%', $container->getParameter('doctrine.odm.mongodb.yml_mapping_dirs'));
        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/YamlBundle/Document'), $container->getParameter('doctrine.odm.mongodb.document_dirs'));

        $calls = $container->getDefinition('doctrine.odm.mongodb.metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.odm.mongodb.metadata_driver.yml', (string) $calls[0][1][0]);
        $this->assertEquals('Fixtures\Bundles\YamlBundle\Document', $calls[0][1][1]);
    }

    public function testXmlBundleMappingDetection()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader('XmlBundle');

        $loader->mongodbLoad(array(), $container);

        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/XmlBundle/Resources/config/doctrine/metadata'), $container->getParameter('doctrine.odm.mongodb.mapping_dirs'));
        $this->assertEquals('%doctrine.odm.mongodb.mapping_dirs%', $container->getParameter('doctrine.odm.mongodb.xml_mapping_dirs'));
        $this->assertEquals('%doctrine.odm.mongodb.mapping_dirs%', $container->getParameter('doctrine.odm.mongodb.yml_mapping_dirs'));
        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/XmlBundle/Document'), $container->getParameter('doctrine.odm.mongodb.document_dirs'));

        $calls = $container->getDefinition('doctrine.odm.mongodb.metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.odm.mongodb.metadata_driver.xml', (string) $calls[0][1][0]);
        $this->assertEquals('Fixtures\Bundles\XmlBundle\Document', $calls[0][1][1]);
    }

    public function testAnnotationsBundleMappingDetection()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader('AnnotationsBundle');

        $loader->mongodbLoad(array(), $container);

        $this->assertEquals(array(), $container->getParameter('doctrine.odm.mongodb.mapping_dirs'));
        $this->assertEquals('%doctrine.odm.mongodb.mapping_dirs%', $container->getParameter('doctrine.odm.mongodb.xml_mapping_dirs'));
        $this->assertEquals('%doctrine.odm.mongodb.mapping_dirs%', $container->getParameter('doctrine.odm.mongodb.yml_mapping_dirs'));
        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/AnnotationsBundle/Document'), $container->getParameter('doctrine.odm.mongodb.document_dirs'));

        $calls = $container->getDefinition('doctrine.odm.mongodb.metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.odm.mongodb.metadata_driver.annotation', (string) $calls[0][1][0]);
        $this->assertEquals('Fixtures\Bundles\AnnotationsBundle\Document', $calls[0][1][1]);
    }

    public function testDocumentManagerMetadataCacheDriverConfiguration()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_multiple_connections');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.odm.mongodb.dm1_metadata_cache');
        $this->assertEquals('%doctrine.odm.mongodb.cache.xcache_class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.odm.mongodb.dm2_metadata_cache');
        $this->assertEquals('%doctrine.odm.mongodb.cache.apc_class%', $definition->getClass());
    }

    public function testDocumentManagerMemcacheMetadataCacheDriverConfiguration()
    {
        $container = new ContainerBuilder();
        $loader = $this->getMongoDbExtensionLoader();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_simple_single_connection');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_metadata_cache');
        $this->assertEquals('Doctrine\Common\Cache\MemcacheCache', $definition->getClass());

        $calls = $definition->getMethodCalls();
        $this->assertEquals('setMemcache', $calls[0][0]);
        $this->assertEquals('doctrine.odm.mongodb.default_memcache_instance', (string) $calls[0][1][0]);

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_memcache_instance');
        $this->assertEquals('Memcache', $definition->getClass());

        $calls = $definition->getMethodCalls();
        $this->assertEquals('connect', $calls[0][0]);
        $this->assertEquals('localhost', $calls[0][1][0]);
        $this->assertEquals(11211, $calls[0][1][1]);
    }

    protected function getMongoDbExtensionLoader($bundle = 'YamlBundle')
    {
        require_once __DIR__.'/Fixtures/Bundles/'.$bundle.'/'.$bundle.'.php';
        $bundleDirs = array('Fixtures\\Bundles' => __DIR__.'/Fixtures/Bundles');
        $bundles = array('Fixtures\\Bundles\\'.$bundle.'\\'.$bundle);
        return new MongoDBExtension($bundleDirs, $bundles, sys_get_temp_dir());
    }
}