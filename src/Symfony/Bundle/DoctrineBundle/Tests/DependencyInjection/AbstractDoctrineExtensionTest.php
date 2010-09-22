<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection;

use Symfony\Bundle\DoctrineBundle\Tests\TestCase;
use Symfony\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

abstract class AbstractDoctrineExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testDbalLoad()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $loader->dbalLoad(array(), $container);
        $this->assertEquals('Symfony\\Bundle\\DoctrineBundle\\DataCollector\\DoctrineDataCollector', $container->getParameter('doctrine.data_collector.class'), '->dbalLoad() loads the dbal.xml file if not already loaded');

        // doctrine.dbal.default_connection
        $this->assertEquals('default', $container->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $loader->dbalLoad(array('default_connection' => 'foo'), $container);
        $this->assertEquals('foo', $container->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $loader->dbalLoad(array(), $container);
        $this->assertEquals('foo', $container->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');

        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $loader->dbalLoad(array('password' => 'foo'), $container);

        $arguments = $container->getDefinition('doctrine.dbal.default_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);

        $loader->dbalLoad(array('user' => 'foo'), $container);
        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);
    }

    public function testDbalLoadFromXmlMultipleConnections()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $loadXml = new XmlFileLoader($container, __DIR__.'/Fixtures/config/xml');
        $loadXml->load('dbal_service_multiple_connections.xml');
        $loader->dbalLoad(array(), $container);
        $container->freeze();

        // doctrine.dbal.mysql_connection
        $arguments = $container->getDefinition('doctrine.dbal.mysql_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);

        // doctrine.dbal.sqlite_connection
        $arguments = $container->getDefinition('doctrine.dbal.sqlite_connection')->getArguments();
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $loadXml = new XmlFileLoader($container, __DIR__.'/Fixtures/config/xml');
        $loadXml->load('dbal_service_single_connection.xml');
        $loader->dbalLoad(array(), $container);
        $container->freeze();

        // doctrine.dbal.mysql_connection
        $arguments = $container->getDefinition('doctrine.dbal.mysql_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);
    }

    public function testDependencyInjectionConfigurationDefaults()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $loader->dbalLoad(array(), $container);
        $loader->ormLoad(array(), $container);

        $this->assertEquals('Doctrine\DBAL\Connection', $container->getParameter('doctrine.dbal.connection_class'));
        $this->assertEquals('Doctrine\ORM\Configuration', $container->getParameter('doctrine.orm.configuration_class'));
        $this->assertEquals('Doctrine\ORM\EntityManager', $container->getParameter('doctrine.orm.entity_manager_class'));
        $this->assertEquals('Proxies', $container->getParameter('doctrine.orm.proxy_namespace'));
        $this->assertEquals(false, $container->getParameter('doctrine.orm.auto_generate_proxy_classes'));
        $this->assertEquals('Doctrine\Common\Cache\ArrayCache', $container->getParameter('doctrine.orm.cache.array_class'));
        $this->assertEquals('Doctrine\Common\Cache\ApcCache', $container->getParameter('doctrine.orm.cache.apc_class'));
        $this->assertEquals('Doctrine\Common\Cache\MemcacheCache', $container->getParameter('doctrine.orm.cache.memcache_class'));
        $this->assertEquals('localhost', $container->getParameter('doctrine.orm.cache.memcache_host'));
        $this->assertEquals('11211', $container->getParameter('doctrine.orm.cache.memcache_port'));
        $this->assertEquals('Memcache', $container->getParameter('doctrine.orm.cache.memcache_instance_class'));
        $this->assertEquals('Doctrine\Common\Cache\XcacheCache', $container->getParameter('doctrine.orm.cache.xcache_class'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\DriverChain', $container->getParameter('doctrine.orm.metadata.driver_chain_class'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\AnnotationDriver', $container->getParameter('doctrine.orm.metadata.annotation_class'));
        $this->assertEquals('Doctrine\Common\Annotations\AnnotationReader', $container->getParameter('doctrine.orm.metadata.annotation_reader_class'));
        $this->assertEquals('Doctrine\ORM\Mapping\\', $container->getParameter('doctrine.orm.metadata.annotation_default_namespace'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\XmlDriver', $container->getParameter('doctrine.orm.metadata.xml_class'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\YamlDriver', $container->getParameter('doctrine.orm.metadata.yml_class'));

        $config = array(
            'proxy_namespace' => 'MyProxies',
            'auto_generate_proxy_classes' => true,
        );
        
        $loader->dbalLoad(array(), $container);
        $loader->ormLoad($config, $container);

        $this->assertEquals('MyProxies', $container->getParameter('doctrine.orm.proxy_namespace'));
        $this->assertEquals(true, $container->getParameter('doctrine.orm.auto_generate_proxy_classes'));

        $definition = $container->getDefinition('doctrine.dbal.default_connection');
        $this->assertEquals('Doctrine\DBAL\DriverManager', $definition->getClass());

        $args = $definition->getArguments();
        $this->assertEquals('Doctrine\DBAL\Driver\PDOMySql\Driver', $args[0]['driverClass']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('root', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.default_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.default_connection.event_manager', (string) $args[2]);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.default_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $calls = $definition->getMethodCalls();
        $this->assertEquals(array('YamlBundle' => 'DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle\Entity'), $calls[0][1][0]);
        $this->assertEquals('doctrine.orm.default_metadata_cache', (string) $calls[1][1][0]);
        $this->assertEquals('doctrine.orm.default_query_cache', (string) $calls[2][1][0]);
        $this->assertEquals('doctrine.orm.default_result_cache', (string) $calls[3][1][0]);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $this->assertEquals('%doctrine.orm.cache.array_class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.default_query_cache');
        $this->assertEquals('%doctrine.orm.cache.array_class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.default_result_cache');
        $this->assertEquals('%doctrine.orm.cache.array_class%', $definition->getClass());
    }

    public function testSingleEntityManagerConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $loader->dbalLoad(array(), $container);
        $loader->ormLoad(array(), $container);

        $definition = $container->getDefinition('doctrine.dbal.default_connection');
        $this->assertEquals('Doctrine\DBAL\DriverManager', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.default_configuration', (string) $arguments[1]);
    }

    public function testLoadSimpleSingleConnection()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $loader->dbalLoad(array(), $container);
        $loader->ormLoad(array(), $container);

        $this->loadFromFile($container, 'orm_service_simple_single_entity_manager');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.dbal.default_connection');
        $this->assertEquals('Doctrine\DBAL\DriverManager', $definition->getClass());

        $arguments = $definition->getArguments();
        $this->assertEquals('Doctrine\DBAL\Driver\PDOMySql\Driver', $arguments[0]['driverClass']);
        $this->assertEquals('localhost', $arguments[0]['host']);
        $this->assertEquals('root', $arguments[0]['user']);
        $this->assertEquals('doctrine.dbal.default_connection.configuration', (string) $arguments[1]);
        $this->assertEquals('doctrine.dbal.default_connection.event_manager', (string) $arguments[2]);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.default_configuration', (string) $arguments[1]);
    }

    public function testLoadSingleConnection()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_single_entity_manager');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.dbal.default_connection');
        $this->assertEquals('Doctrine\DBAL\DriverManager', $definition->getClass());

        $args = $definition->getArguments();
        $this->assertEquals('Doctrine\DBAL\Driver\PDOSqlite\Driver', $args[0]['driverClass']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.default_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.default_connection.event_manager', (string) $args[2]);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.default_configuration', (string) $arguments[1]);
    }

    public function testLoadMultipleConnections()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_multiple_entity_managers');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.dbal.conn1_connection');
        $this->assertEquals('Doctrine\DBAL\DriverManager', $definition->getClass());

        $args = $definition->getArguments();
        $this->assertEquals('Doctrine\DBAL\Driver\PDOSqlite\Driver', $args[0]['driverClass']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.conn1_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.conn1_connection.event_manager', (string) $args[2]);

        $this->assertEquals('doctrine.orm.dm2_entity_manager', $container->getAlias('doctrine.orm.entity_manager'));

        $definition = $container->getDefinition('doctrine.orm.dm1_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.conn1_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.dm1_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.dbal.conn2_connection');
        $this->assertEquals('Doctrine\DBAL\DriverManager', $definition->getClass());

        $args = $definition->getArguments();
        $this->assertEquals('Doctrine\DBAL\Driver\PDOSqlite\Driver', $args[0]['driverClass']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.conn2_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.conn2_connection.event_manager', (string) $args[2]);

        $definition = $container->getDefinition('doctrine.orm.dm2_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager_class%', $definition->getClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.conn2_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.dm2_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.orm.dm1_metadata_cache');
        $this->assertEquals('%doctrine.orm.cache.xcache_class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.dm1_query_cache');
        $this->assertEquals('%doctrine.orm.cache.array_class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.dm1_result_cache');
        $this->assertEquals('%doctrine.orm.cache.array_class%', $definition->getClass());
    }

    public function testBundleEntityAliases()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $loader->dbalLoad(array(), $container);
        $loader->ormLoad(array(), $container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $calls = $definition->getMethodCalls();
        $this->assertTrue(isset($calls[0][1][0]['YamlBundle']));
        $this->assertEquals('DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle\Entity', $calls[0][1][0]['YamlBundle']);
    }

    public function testYamlBundleMappingDetection()
    {
        $container = $this->getContainer('YamlBundle');
        $loader = new DoctrineExtension();

        $loader->dbalLoad(array(), $container);
        $loader->ormLoad(array(), $container);

        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/YamlBundle/Resources/config/doctrine/metadata/orm'), $container->getParameter('doctrine.orm.metadata_driver.mapping_dirs'));
        $this->assertEquals('%doctrine.orm.metadata_driver.mapping_dirs%', $container->getParameter('doctrine.orm.xml_mapping_dirs'));
        $this->assertEquals('%doctrine.orm.metadata_driver.mapping_dirs%', $container->getParameter('doctrine.orm.yml_mapping_dirs'));
        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/YamlBundle/Entity'), $container->getParameter('doctrine.orm.metadata_driver.entity_dirs'));

        $calls = $container->getDefinition('doctrine.orm.metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.orm.metadata_driver.yml', (string) $calls[0][1][0]);
        $this->assertEquals('DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle\Entity', $calls[0][1][1]);
    }

    public function testXmlBundleMappingDetection()
    {
        $container = $this->getContainer('XmlBundle');
        $loader = new DoctrineExtension();

        $loader->dbalLoad(array(), $container);
        $loader->ormLoad(array(), $container);

        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/XmlBundle/Resources/config/doctrine/metadata/orm'), $container->getParameter('doctrine.orm.metadata_driver.mapping_dirs'));
        $this->assertEquals('%doctrine.orm.metadata_driver.mapping_dirs%', $container->getParameter('doctrine.orm.xml_mapping_dirs'));
        $this->assertEquals('%doctrine.orm.metadata_driver.mapping_dirs%', $container->getParameter('doctrine.orm.yml_mapping_dirs'));
        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/XmlBundle/Entity'), $container->getParameter('doctrine.orm.metadata_driver.entity_dirs'));

        $calls = $container->getDefinition('doctrine.orm.metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.orm.metadata_driver.xml', (string) $calls[0][1][0]);
        $this->assertEquals('DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\XmlBundle\Entity', $calls[0][1][1]);
    }

    public function testAnnotationsBundleMappingDetection()
    {
        $container = $this->getContainer('AnnotationsBundle');
        $loader = new DoctrineExtension();

        $loader->dbalLoad(array(), $container);
        $loader->ormLoad(array(), $container);

        $this->assertEquals(array(), $container->getParameter('doctrine.orm.metadata_driver.mapping_dirs'));
        $this->assertEquals('%doctrine.orm.metadata_driver.mapping_dirs%', $container->getParameter('doctrine.orm.xml_mapping_dirs'));
        $this->assertEquals('%doctrine.orm.metadata_driver.mapping_dirs%', $container->getParameter('doctrine.orm.yml_mapping_dirs'));
        $this->assertEquals(array(__DIR__.'/Fixtures/Bundles/AnnotationsBundle/Entity'), $container->getParameter('doctrine.orm.metadata_driver.entity_dirs'));

        $calls = $container->getDefinition('doctrine.orm.metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.orm.metadata_driver.annotation', (string) $calls[0][1][0]);
        $this->assertEquals('DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\AnnotationsBundle\Entity', $calls[0][1][1]);
    }

    public function testEntityManagerMetadataCacheDriverConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_multiple_entity_managers');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.orm.dm1_metadata_cache');
        $this->assertEquals('%doctrine.orm.cache.xcache_class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.dm2_metadata_cache');
        $this->assertEquals('%doctrine.orm.cache.apc_class%', $definition->getClass());
    }

    public function testEntityManagerMemcacheMetadataCacheDriverConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_simple_single_entity_manager');

        $container->freeze();

        $definition = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $this->assertEquals('Doctrine\Common\Cache\MemcacheCache', $definition->getClass());

        $calls = $definition->getMethodCalls();
        $this->assertEquals('setMemcache', $calls[0][0]);
        $this->assertEquals('doctrine.orm.default_memcache_instance', (string) $calls[0][1][0]);

        $definition = $container->getDefinition('doctrine.orm.default_memcache_instance');
        $this->assertEquals('Memcache', $definition->getClass());

        $calls = $definition->getMethodCalls();
        $this->assertEquals('connect', $calls[0][0]);
        $this->assertEquals('localhost', $calls[0][1][0]);
        $this->assertEquals(11211, $calls[0][1][1]);
    }

    public function testDependencyInjectionImportsOverrideDefaults()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_imports');

        $container->freeze();

        $this->assertEquals('apc', $container->getParameter('doctrine.orm.metadata_cache_driver'));
        $this->assertTrue($container->getParameter('doctrine.orm.auto_generate_proxy_classes'));
    }

    protected function getContainer($bundle = 'YamlBundle')
    {
        require_once __DIR__.'/Fixtures/Bundles/'.$bundle.'/'.$bundle.'.php';

        return new ContainerBuilder(new ParameterBag(array(
            'kernel.bundle_dirs' => array('DoctrineBundle\\Tests\\DependencyInjection\\Fixtures\\Bundles' => __DIR__.'/Fixtures/Bundles'),
            'kernel.bundles'     => array('DoctrineBundle\\Tests\DependencyInjection\\Fixtures\\Bundles\\'.$bundle.'\\'.$bundle),
            'kernel.cache_dir'   => sys_get_temp_dir(),
        )));
    }
}
