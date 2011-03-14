<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Tests\DependencyInjection;

use Symfony\Bundle\DoctrineMongoDBBundle\Tests\TestCase;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\DoctrineMongoDBExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

abstract class AbstractMongoDBExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testDependencyInjectionConfigurationDefaults()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();

        $loader->load(array(array()), $container);

        $this->assertEquals('Doctrine\MongoDB\Connection', $container->getParameter('doctrine.odm.mongodb.connection_class'));
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
        $this->assertEquals('Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver', $container->getParameter('doctrine.odm.mongodb.metadata.xml_class'));
        $this->assertEquals('Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver', $container->getParameter('doctrine.odm.mongodb.metadata.yml_class'));

        $this->assertEquals('Symfony\Bundle\DoctrineMongoDBBundle\Validator\Constraints\UniqueValidator', $container->getParameter('doctrine_odm.mongodb.validator.unique.class'));

        $config = array(
            'proxy_namespace' => 'MyProxies',
            'auto_generate_proxy_classes' => true,
            'connections' => array('default' => array()),
            'document_managers' => array('default' => array())
        );
        $loader->load(array($config), $container);

        $this->assertEquals('MyProxies', $container->getParameter('doctrine.odm.mongodb.proxy_namespace'));
        $this->assertEquals(true, $container->getParameter('doctrine.odm.mongodb.auto_generate_proxy_classes'));

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array(null, array(), new Reference('doctrine.odm.mongodb.default_configuration')), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());
        $this->assertArrayHasKey('doctrine.odm.mongodb.document_manager', $definition->getTags());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.default_configuration', (string) $arguments[1]);
    }

    public function testSingleDocumentManagerConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();

        $config = array(
            'connections' => array(
                'default' => array(
                    'server' => 'mongodb://localhost:27017',
                    'options' => array('connect' => true)
                )
            ),
            'document_managers' => array('default' => array())
        );
        $loader->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true), new Reference('doctrine.odm.mongodb.default_configuration')), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());
        $this->assertArrayHasKey('doctrine.odm.mongodb.document_manager', $definition->getTags());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.default_configuration', (string) $arguments[1]);
    }

    public function testLoadSimpleSingleConnection()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_simple_single_connection');

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true), new Reference('doctrine.odm.mongodb.default_configuration')), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_configuration');
        $methodCalls = $definition->getMethodCalls();
        $methodNames = array_map(function($call) { return $call[0]; }, $methodCalls);
        $this->assertInternalType('integer', $pos = array_search('setDefaultDB', $methodNames));
        $this->assertEquals('mydb', $methodCalls[$pos][1][0]);

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());
        $this->assertArrayHasKey('doctrine.odm.mongodb.document_manager', $definition->getTags());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.default_configuration', (string) $arguments[1]);
    }

    public function testLoadSingleConnection()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_single_connection');

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true), new Reference('doctrine.odm.mongodb.default_configuration')), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());
        $this->assertArrayHasKey('doctrine.odm.mongodb.document_manager', $definition->getTags());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.default_configuration', (string) $arguments[1]);
    }

    public function testLoadMultipleConnections()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_multiple_connections');

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        $definition = $container->getDefinition('doctrine.odm.mongodb.conn1_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true), new Reference('doctrine.odm.mongodb.conn1_configuration')), $definition->getArguments());

        $this->assertEquals('doctrine.odm.mongodb.dm2_document_manager', (string) $container->getAlias('doctrine.odm.mongodb.document_manager'));

        $definition = $container->getDefinition('doctrine.odm.mongodb.dm1_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());
        $this->assertArrayHasKey('doctrine.odm.mongodb.document_manager', $definition->getTags());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.conn1_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.dm1_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.odm.mongodb.conn2_connection');
        $this->assertEquals('%doctrine.odm.mongodb.connection_class%', $definition->getClass());
        $this->assertEquals(array('mongodb://localhost:27017', array('connect' => true), new Reference('doctrine.odm.mongodb.conn2_configuration')), $definition->getArguments());

        $definition = $container->getDefinition('doctrine.odm.mongodb.dm2_document_manager');
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getClass());
        $this->assertEquals('%doctrine.odm.mongodb.document_manager_class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());
        $this->assertArrayHasKey('doctrine.odm.mongodb.document_manager', $definition->getTags());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.odm.mongodb.conn2_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.odm.mongodb.dm2_configuration', (string) $arguments[1]);
    }

    public function testBundleDocumentAliases()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();

        $loader->load(array(array('document_managers' => array('default' => array('mappings' => array('YamlBundle' => array()))))), $container);

        $definition = $container->getDefinition('doctrine.odm.mongodb.default_configuration');
        $calls = $definition->getMethodCalls();
        $this->assertTrue(isset($calls[0][1][0]['YamlBundle']));
        $this->assertEquals('DoctrineMongoDBBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle\Document', $calls[0][1][0]['YamlBundle']);
    }

    public function testYamlBundleMappingDetection()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension('YamlBundle');

        $loader->load(array(array('document_managers' => array('default' => array('mappings' => array('YamlBundle' => array()))))), $container);

        $calls = $container->getDefinition('doctrine.odm.mongodb.default_metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.odm.mongodb.default_yml_metadata_driver', (string) $calls[0][1][0]);
        $this->assertEquals('DoctrineMongoDBBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle\Document', $calls[0][1][1]);
    }

    public function testXmlBundleMappingDetection()
    {
        $container = $this->getContainer('XmlBundle');
        $loader = new DoctrineMongoDBExtension();

        $loader->load(array(array('document_managers' => array('default' => array('mappings' => array('XmlBundle' => array()))))), $container);

        $calls = $container->getDefinition('doctrine.odm.mongodb.default_metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.odm.mongodb.default_xml_metadata_driver', (string) $calls[0][1][0]);
        $this->assertEquals('DoctrineMongoDBBundle\Tests\DependencyInjection\Fixtures\Bundles\XmlBundle\Document', $calls[0][1][1]);
    }

    public function testAnnotationsBundleMappingDetection()
    {
        $container = $this->getContainer('AnnotationsBundle');
        $loader = new DoctrineMongoDBExtension();

        $loader->load(array(array('document_managers' => array('default' => array('mappings' => array('AnnotationsBundle' => array()))))), $container);

        $calls = $container->getDefinition('doctrine.odm.mongodb.default_metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.odm.mongodb.default_annotation_metadata_driver', (string) $calls[0][1][0]);
        $this->assertEquals('DoctrineMongoDBBundle\Tests\DependencyInjection\Fixtures\Bundles\AnnotationsBundle\Document', $calls[0][1][1]);
    }

    public function testDocumentManagerMetadataCacheDriverConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_multiple_connections');

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        $definition = $container->getDefinition('doctrine.odm.mongodb.dm1_metadata_cache');
        $this->assertEquals('%doctrine.odm.mongodb.cache.xcache_class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.odm.mongodb.dm2_metadata_cache');
        $this->assertEquals('%doctrine.odm.mongodb.cache.apc_class%', $definition->getClass());
    }

    public function testDocumentManagerMemcacheMetadataCacheDriverConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'mongodb_service_simple_single_connection');

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

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

    public function testDependencyInjectionImportsOverrideDefaults()
    {
        $container = $this->getContainer();
        $loader = new DoctrineMongoDBExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'odm_imports');

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        $this->assertTrue($container->getParameter('doctrine.odm.mongodb.auto_generate_proxy_classes'));
    }

    public function testRegistersValidatorNamespace()
    {
        $container = $this->getContainer();

        $container->setParameter('validator.annotations.namespaces', array('Namespace1\\', 'Namespace2\\'));

        $loader = new DoctrineMongoDBExtension();

        $loader->load(array(array()), $container);

        $this->assertEquals(array(
            'Namespace1\\',
            'Namespace2\\',
            'mongodb' => 'Symfony\Bundle\DoctrineMongoDBBundle\Validator\Constraints\\',
        ), $container->getParameter('validator.annotations.namespaces'));
    }

    protected function getContainer($bundle = 'YamlBundle')
    {
        require_once __DIR__.'/Fixtures/Bundles/'.$bundle.'/'.$bundle.'.php';


        return new ContainerBuilder(new ParameterBag(array(
            'kernel.bundles'     => array($bundle => 'DoctrineMongoDBBundle\\Tests\\DependencyInjection\\Fixtures\\Bundles\\'.$bundle.'\\'.$bundle),
            'kernel.cache_dir'   => sys_get_temp_dir(),
            'kernel.debug'       => false,
        )));
    }
}
