<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\ResolveDefinitionTemplatesPass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Config\FileLocator;

abstract class AbstractDoctrineExtensionTest extends TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

    public function testDbalOverrideDefaultConnection()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $loader->load(array(array(), array('dbal' => array('default_connection' => 'foo')), array()), $container);

        // doctrine.dbal.default_connection
        $this->assertEquals('%doctrine.default_connection%', $container->getDefinition('doctrine')->getArgument(3), '->load() overrides existing configuration options');
        $this->assertEquals('foo', $container->getParameter('doctrine.default_connection'), '->load() overrides existing configuration options');

    }

    public function testDbalLoad()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $loader->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo')))), array(), array('dbal' => array('default_connection' => 'foo')), array()), $container);

        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);
    }

    public function testDbalLoadFromXmlMultipleConnections()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $loadXml = new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/config/xml'));
        $loadXml->load('dbal_service_multiple_connections.xml');
        $loader->load(array(array()), $container);

        $this->compileContainer($container);

        // doctrine.dbal.mysql_connection
        $config = $container->getDefinition('doctrine.dbal.mysql_connection')->getArgument(0);

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);

        // doctrine.dbal.sqlite_connection
        $config = $container->getDefinition('doctrine.dbal.sqlite_connection')->getArgument(0);
        $this->assertArrayHasKey('memory', $config);

        // doctrine.dbal.oci8_connection
        $config = $container->getDefinition('doctrine.dbal.oci_connection')->getArgument(0);
        $this->assertArrayHasKey('charset', $config);
    }

    public function testDbalLoadFromXmlSingleConnections()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $loadXml = new XmlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/config/xml'));
        $loadXml->load('dbal_service_single_connection.xml');
        $loader->load(array(array()), $container);

        $this->compileContainer($container);

        // doctrine.dbal.mysql_connection
        $config = $container->getDefinition('doctrine.dbal.default_connection')->getArgument(0);

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);
    }

    public function testDependencyInjectionConfigurationDefaults()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $loader->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo'))), 'orm' => array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array())))))), $container);

        $this->assertFalse($container->getParameter('doctrine.orm.auto_generate_proxy_classes'));
        $this->assertEquals('Doctrine\ORM\Configuration', $container->getParameter('doctrine.orm.configuration.class'));
        $this->assertEquals('Doctrine\ORM\EntityManager', $container->getParameter('doctrine.orm.entity_manager.class'));
        $this->assertEquals('Proxies', $container->getParameter('doctrine.orm.proxy_namespace'));
        $this->assertEquals('Doctrine\Common\Cache\ArrayCache', $container->getParameter('doctrine.orm.cache.array.class'));
        $this->assertEquals('Doctrine\Common\Cache\ApcCache', $container->getParameter('doctrine.orm.cache.apc.class'));
        $this->assertEquals('Doctrine\Common\Cache\MemcacheCache', $container->getParameter('doctrine.orm.cache.memcache.class'));
        $this->assertEquals('localhost', $container->getParameter('doctrine.orm.cache.memcache_host'));
        $this->assertEquals('11211', $container->getParameter('doctrine.orm.cache.memcache_port'));
        $this->assertEquals('Memcache', $container->getParameter('doctrine.orm.cache.memcache_instance.class'));
        $this->assertEquals('Doctrine\Common\Cache\XcacheCache', $container->getParameter('doctrine.orm.cache.xcache.class'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\DriverChain', $container->getParameter('doctrine.orm.metadata.driver_chain.class'));
        $this->assertEquals('Doctrine\ORM\Mapping\Driver\AnnotationDriver', $container->getParameter('doctrine.orm.metadata.annotation.class'));
        $this->assertEquals('Symfony\Bridge\Doctrine\Mapping\Driver\XmlDriver', $container->getParameter('doctrine.orm.metadata.xml.class'));
        $this->assertEquals('Symfony\Bridge\Doctrine\Mapping\Driver\YamlDriver', $container->getParameter('doctrine.orm.metadata.yml.class'));

        $config = array(
            'proxy_namespace' => 'MyProxies',
            'auto_generate_proxy_classes' => true,
            'default_entity_manager' => 'default',
            'entity_managers' => array(
                'default' => array(
                    'mappings' => array('YamlBundle' => array()),
                    )
                )
        );

        $container = $this->getContainer();
        $loader->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo'))), 'orm' => $config)), $container);
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_mysql', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('root', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.default_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.default_connection.event_manager', (string) $args[2]);

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $this->assertEquals(array('default' => 'doctrine.orm.default_entity_manager'), $container->getParameter('doctrine.entity_managers'), "Set of the existing EntityManagers names is incorrect.");
        $this->assertEquals('%doctrine.entity_managers%', $container->getDefinition('doctrine')->getArgument(2), "Set of the existing EntityManagers names is incorrect.");

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.default_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.default_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $calls = array_values($definition->getMethodCalls());
        $this->assertEquals(array('YamlBundle' => 'Fixtures\Bundles\YamlBundle\Entity'), $calls[0][1][0]);
        $this->assertEquals('doctrine.orm.default_metadata_cache', (string) $calls[1][1][0]);
        $this->assertEquals('doctrine.orm.default_query_cache', (string) $calls[2][1][0]);
        $this->assertEquals('doctrine.orm.default_result_cache', (string) $calls[3][1][0]);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $this->assertEquals('%doctrine.orm.cache.array.class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.default_query_cache');
        $this->assertEquals('%doctrine.orm.cache.array.class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.default_result_cache');
        $this->assertEquals('%doctrine.orm.cache.array.class%', $definition->getClass());
    }

    public function testSingleEntityManagerConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $loader->load(array(array('dbal' => array('connections' => array('default' => array('password' => 'foo'))), 'orm' => array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array())))))), $container);
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $this->assertDICConstructorArguments($definition, array(
            new Reference('doctrine.dbal.default_connection'), new Reference('doctrine.orm.default_configuration')
        ));
    }

    public function testLoadSimpleSingleConnection()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_simple_single_entity_manager');

        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $this->assertDICConstructorArguments($definition, array(
            array(
                'dbname' => 'db',
                'host' => 'localhost',
                'port' => null,
                'user' => 'root',
                'password' => null,
                'driver' => 'pdo_mysql',
                'logging' => false,
                'driverOptions' => array(),
            ),
            new Reference('doctrine.dbal.default_connection.configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
            array(),
        ));

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $this->assertDICConstructorArguments($definition, array(
            new Reference('doctrine.dbal.default_connection'), new Reference('doctrine.orm.default_configuration')
        ));
    }

    public function testLoadSingleConnection()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_single_entity_manager');

        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.dbal.default_connection');

        $this->assertDICConstructorArguments($definition, array(
            array(
                'host' => 'localhost',
                'driver' => 'pdo_sqlite',
                'driverOptions' => array(),
                'user' => 'sqlite_user',
                'port' => null,
                'password' => 'sqlite_s3cr3t',
                'dbname' => 'sqlite_db',
                'memory' => true,
                'logging' => false,
            ),
            new Reference('doctrine.dbal.default_connection.configuration'),
            new Reference('doctrine.dbal.default_connection.event_manager'),
            array(),
        ));

        $definition = $container->getDefinition('doctrine.orm.default_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $this->assertDICConstructorArguments($definition, array(
            new Reference('doctrine.dbal.default_connection'), new Reference('doctrine.orm.default_configuration')
        ));
    }

    public function testLoadMultipleConnections()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_multiple_entity_managers');

        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.dbal.conn1_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_sqlite', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.conn1_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.conn1_connection.event_manager', (string) $args[2]);

        $this->assertEquals('doctrine.orm.em2_entity_manager', (string) $container->getAlias('doctrine.orm.entity_manager'));

        $definition = $container->getDefinition('doctrine.orm.em1_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.conn1_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.em1_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.dbal.conn2_connection');

        $args = $definition->getArguments();
        $this->assertEquals('pdo_sqlite', $args[0]['driver']);
        $this->assertEquals('localhost', $args[0]['host']);
        $this->assertEquals('sqlite_user', $args[0]['user']);
        $this->assertEquals('doctrine.dbal.conn2_connection.configuration', (string) $args[1]);
        $this->assertEquals('doctrine.dbal.conn2_connection.event_manager', (string) $args[2]);

        $definition = $container->getDefinition('doctrine.orm.em2_entity_manager');
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getClass());
        $this->assertEquals('%doctrine.orm.entity_manager.class%', $definition->getFactoryClass());
        $this->assertEquals('create', $definition->getFactoryMethod());

        $arguments = $definition->getArguments();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[0]);
        $this->assertEquals('doctrine.dbal.conn2_connection', (string) $arguments[0]);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Reference', $arguments[1]);
        $this->assertEquals('doctrine.orm.em2_configuration', (string) $arguments[1]);

        $definition = $container->getDefinition('doctrine.orm.em1_metadata_cache');
        $this->assertEquals('%doctrine.orm.cache.xcache.class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.em1_query_cache');
        $this->assertEquals('%doctrine.orm.cache.array.class%', $definition->getClass());

        $definition = $container->getDefinition('doctrine.orm.em1_result_cache');
        $this->assertEquals('%doctrine.orm.cache.array.class%', $definition->getClass());
    }

    public function testBundleEntityAliases()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array()))));
        $loader->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setEntityNamespaces',
            array(array('YamlBundle' => 'Fixtures\Bundles\YamlBundle\Entity'))
        );
    }

    public function testOverwriteEntityAliases()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array('alias' => 'yml')))));
        $loader->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setEntityNamespaces',
            array(array('yml' => 'Fixtures\Bundles\YamlBundle\Entity'))
        );
    }

    public function testYamlBundleMappingDetection()
    {
        $container = $this->getContainer('YamlBundle');
        $loader = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('YamlBundle' => array()))));
        $loader->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addDriver', array(
            new Reference('doctrine.orm.default_yml_metadata_driver'),
            'Fixtures\Bundles\YamlBundle\Entity'
        ));
    }

    public function testXmlBundleMappingDetection()
    {
        $container = $this->getContainer('XmlBundle');
        $loader = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('XmlBundle' => array()))));
        $loader->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addDriver', array(
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle\Entity'
        ));
    }

    public function testAnnotationsBundleMappingDetection()
    {
        $container = $this->getContainer('AnnotationsBundle');
        $loader = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('AnnotationsBundle' => array()))));
        $loader->load(array($config), $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addDriver', array(
            new Reference('doctrine.orm.default_annotation_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity'
        ));
    }

    public function testOrmMergeConfigs()
    {
        $container = $this->getContainer(array('XmlBundle', 'AnnotationsBundle'));
        $loader = new DoctrineExtension();

        $config1 = $this->getConnectionConfig();
        $config1['orm'] = array(
            'auto_generate_proxy_classes' => true,
            'default_entity_manager' => 'default',
            'entity_managers' => array(
                'default' => array('mappings' => array('AnnotationsBundle' => array()))
        ));
        $config2 = $this->getConnectionConfig();
        $config2['orm'] = array(
            'auto_generate_proxy_classes' => false,
            'default_entity_manager' => 'default',
            'entity_managers' => array(
                'default' => array('mappings' => array('XmlBundle' => array()))
        ));
        $loader->load(array($config1, $config2), $container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');
        $this->assertDICDefinitionMethodCallAt(0, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_annotation_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity'
        ));
        $this->assertDICDefinitionMethodCallAt(1, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle\Entity'
        ));

        $configDef = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($configDef, 'setAutoGenerateProxyClasses');

        $calls = $configDef->getMethodCalls();
        foreach ($calls as $call) {
            if ($call[0] == 'setAutoGenerateProxyClasses') {
                $this->assertFalse($container->getParameterBag()->resolveValue($call[1][0]));
                break;
            }
        }
    }

    public function testEntityManagerMetadataCacheDriverConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_multiple_entity_managers');

        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.em1_metadata_cache');
        $this->assertDICDefinitionClass($definition, '%doctrine.orm.cache.xcache.class%');

        $definition = $container->getDefinition('doctrine.orm.em2_metadata_cache');
        $this->assertDICDefinitionClass($definition, '%doctrine.orm.cache.apc.class%');
    }

    public function testEntityManagerMemcacheMetadataCacheDriverConfiguration()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_service_simple_single_entity_manager');

        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $this->assertDICDefinitionClass($definition, 'Doctrine\Common\Cache\MemcacheCache');
        $this->assertDICDefinitionMethodCallOnce($definition, 'setMemcache',
            array(new Reference('doctrine.orm.default_memcache_instance'))
        );

        $definition = $container->getDefinition('doctrine.orm.default_memcache_instance');
        $this->assertDICDefinitionClass($definition, 'Memcache');
        $this->assertDICDefinitionMethodCallOnce($definition, 'connect', array(
            'localhost', '11211'
        ));
    }

    public function testDependencyInjectionImportsOverrideDefaults()
    {
        $container = $this->getContainer();
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_imports');

        $this->compileContainer($container);

        $cacheDefinition = $container->getDefinition('doctrine.orm.default_metadata_cache');
        $this->assertEquals('%doctrine.orm.cache.apc.class%', $cacheDefinition->getClass());

        $configDefinition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($configDefinition, 'setAutoGenerateProxyClasses', array('%doctrine.orm.auto_generate_proxy_classes%'));
    }

    public function testSingleEntityManagerMultipleMappingBundleDefinitions()
    {
        $container = $this->getContainer(array('YamlBundle', 'AnnotationsBundle', 'XmlBundle'));

        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_single_em_bundle_mappings');

        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_metadata_driver');

        $this->assertDICDefinitionMethodCallAt(0, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_annotation_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity'
        ));

        $this->assertDICDefinitionMethodCallAt(1, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_yml_metadata_driver'),
            'Fixtures\Bundles\YamlBundle\Entity'
        ));

        $this->assertDICDefinitionMethodCallAt(2, $definition, 'addDriver', array(
            new Reference('doctrine.orm.default_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle'
        ));

        $annDef = $container->getDefinition('doctrine.orm.default_annotation_metadata_driver');
        $this->assertDICConstructorArguments($annDef, array(
            new Reference('doctrine.orm.metadata.annotation_reader'),
            array(__DIR__ .DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'AnnotationsBundle'.DIRECTORY_SEPARATOR.'Entity')
        ));

        $ymlDef = $container->getDefinition('doctrine.orm.default_yml_metadata_driver');
        $this->assertDICConstructorArguments($ymlDef, array(
            array(__DIR__ .DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'YamlBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'doctrine')
        ));

        $xmlDef = $container->getDefinition('doctrine.orm.default_xml_metadata_driver');
        $this->assertDICConstructorArguments($xmlDef, array(
            array(__DIR__ .DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'XmlBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'doctrine')
        ));
    }

    public function testMultipleEntityManagersMappingBundleDefinitions()
    {
        $container = $this->getContainer(array('YamlBundle', 'AnnotationsBundle', 'XmlBundle'));

        $loader = new DoctrineExtension();
        $container->registerExtension($loader);

        $this->loadFromFile($container, 'orm_multiple_em_bundle_mappings');

        $this->compileContainer($container);

        $this->assertEquals(array('em1' => 'doctrine.orm.em1_entity_manager', 'em2' => 'doctrine.orm.em2_entity_manager'), $container->getParameter('doctrine.entity_managers'), "Set of the existing EntityManagers names is incorrect.");
        $this->assertEquals('%doctrine.entity_managers%', $container->getDefinition('doctrine')->getArgument(2), "Set of the existing EntityManagers names is incorrect.");

        $def1 = $container->getDefinition('doctrine.orm.em1_metadata_driver');
        $def2 = $container->getDefinition('doctrine.orm.em2_metadata_driver');

        $this->assertDICDefinitionMethodCallAt(0, $def1, 'addDriver', array(
            new Reference('doctrine.orm.em1_annotation_metadata_driver'),
            'Fixtures\Bundles\AnnotationsBundle\Entity'
        ));

        $this->assertDICDefinitionMethodCallAt(0, $def2, 'addDriver', array(
            new Reference('doctrine.orm.em2_yml_metadata_driver'),
            'Fixtures\Bundles\YamlBundle\Entity'
        ));

        $this->assertDICDefinitionMethodCallAt(1, $def2, 'addDriver', array(
            new Reference('doctrine.orm.em2_xml_metadata_driver'),
            'Fixtures\Bundles\XmlBundle'
        ));

        $annDef = $container->getDefinition('doctrine.orm.em1_annotation_metadata_driver');
        $this->assertDICConstructorArguments($annDef, array(
            new Reference('doctrine.orm.metadata.annotation_reader'),
            array(__DIR__ .DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'AnnotationsBundle'.DIRECTORY_SEPARATOR.'Entity')
        ));

        $ymlDef = $container->getDefinition('doctrine.orm.em2_yml_metadata_driver');
        $this->assertDICConstructorArguments($ymlDef, array(
            array(__DIR__ .DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'YamlBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'doctrine')
        ));

        $xmlDef = $container->getDefinition('doctrine.orm.em2_xml_metadata_driver');
        $this->assertDICConstructorArguments($xmlDef, array(
            array(__DIR__ .DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Bundles'.DIRECTORY_SEPARATOR.'XmlBundle'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'doctrine')
        ));
    }

    public function testAnnotationsBundleMappingDetectionWithVendorNamespace()
    {
        $container = $this->getContainer('AnnotationsBundle', 'Vendor');
        $loader = new DoctrineExtension();

        $config = $this->getConnectionConfig();
        $config['orm'] = array('default_entity_manager' => 'default', 'entity_managers' => array('default' => array('mappings' => array('AnnotationsBundle' => array()))));
        $loader->load(array($config), $container);

        $calls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        $this->assertEquals('doctrine.orm.default_annotation_metadata_driver', (string) $calls[0][1][0]);
        $this->assertEquals('Fixtures\Bundles\Vendor\AnnotationsBundle\Entity', $calls[0][1][1]);
    }

    public function testSetTypes()
    {
        $container = $this->getContainer(array('YamlBundle'));

        $loader = new DoctrineExtension();
        $container->registerExtension($loader);
        $this->loadFromFile($container, 'dbal_types');
        $this->compileContainer($container);

        $this->assertEquals(
            array('test' => 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestType'),
            $container->getParameter('doctrine.dbal.connection_factory.types')
        );
        $this->assertEquals('%doctrine.dbal.connection_factory.types%', $container->getDefinition('doctrine.dbal.connection_factory')->getArgument(0));
    }

    public function testSetCustomFunctions()
    {
        $container = $this->getContainer(array('YamlBundle'));

        $loader = new DoctrineExtension();
        $container->registerExtension($loader);
        $this->loadFromFile($container, 'orm_functions');
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomStringFunction', array('test_string', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestStringFunction'));
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomNumericFunction', array('test_numeric', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestNumericFunction'));
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomDatetimeFunction', array('test_datetime', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestDatetimeFunction'));
    }

    public function testSingleEMSetCustomFunctions()
    {
        $container = $this->getContainer(array('YamlBundle'));

        $loader = new DoctrineExtension();
        $container->registerExtension($loader);
        $this->loadFromFile($container, 'orm_single_em_dql_functions');
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomStringFunction', array('test_string', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestStringFunction'));
    }

    public function testAddCustomHydrationMode()
    {
        $container = $this->getContainer(array('YamlBundle'));

        $loader = new DoctrineExtension();
        $container->registerExtension($loader);
        $this->loadFromFile($container, 'orm_hydration_mode');
        $this->compileContainer($container);

        $definition = $container->getDefinition('doctrine.orm.default_configuration');
        $this->assertDICDefinitionMethodCallOnce($definition, 'addCustomHydrationMode', array('test_hydrator', 'Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection\TestHydrator'));
    }

    protected function getContainer($bundles = 'YamlBundle', $vendor = null)
    {
        $bundles = (array) $bundles;

        $map = array();
        foreach ($bundles as $bundle) {
            require_once __DIR__.'/Fixtures/Bundles/'.($vendor ? $vendor.'/' : '').$bundle.'/'.$bundle.'.php';

            $map[$bundle] = 'Fixtures\\Bundles\\'.($vendor ? $vendor.'\\' : '').$bundle.'\\'.$bundle;
        }

        return new ContainerBuilder(new ParameterBag(array(
            'kernel.debug'       => false,
            'kernel.bundles'     => $map,
            'kernel.cache_dir'   => sys_get_temp_dir(),
            'kernel.environment' => 'test',
            'kernel.root_dir'    => __DIR__.'/../../../../../' // src dir
        )));
    }

    /**
     * Assertion on the Class of a DIC Service Definition.
     *
     * @param Definition $definition
     * @param string     $expectedClass
     */
    protected function assertDICDefinitionClass($definition, $expectedClass)
    {
        $this->assertEquals($expectedClass, $definition->getClass(), 'Expected Class of the DIC Container Service Definition is wrong.');
    }

    protected function assertDICConstructorArguments($definition, $args)
    {
        $this->assertEquals($args, $definition->getArguments(), "Expected and actual DIC Service constructor arguments of definition '".$definition->getClass()."' don't match.");
    }

    protected function assertDICDefinitionMethodCallAt($pos, $definition, $methodName, array $params = null)
    {
        $calls = $definition->getMethodCalls();
        if (isset($calls[$pos][0])) {
            $this->assertEquals($methodName, $calls[$pos][0], "Method '".$methodName."' is expected to be called at position $pos.");

            if ($params !== null) {
                $this->assertEquals($params, $calls[$pos][1], "Expected parameters to methods '".$methodName."' do not match the actual parameters.");
            }
        }
    }

    /**
     * Assertion for the DI Container, check if the given definition contains a method call with the given parameters.
     *
     * @param \Symfony\Component\DependencyInjection\Definition $definition
     * @param string                                            $methodName
     * @param array                                             $params
     * @return void
     */
    protected function assertDICDefinitionMethodCallOnce($definition, $methodName, array $params = null)
    {
        $calls = $definition->getMethodCalls();
        $called = false;
        foreach ($calls as $call) {
            if ($call[0] == $methodName) {
                if ($called) {
                    $this->fail("Method '".$methodName."' is expected to be called only once, a second call was registered though.");
                } else {
                    $called = true;
                    if ($params !== null) {
                        $this->assertEquals($params, $call[1], "Expected parameters to methods '".$methodName."' do not match the actual parameters.");
                    }
                }
            }
        }
        if (!$called) {
            $this->fail("Method '".$methodName."' is expected to be called once, definition does not contain a call though.");
        }
    }

    protected function compileContainer(ContainerBuilder $container)
    {
        $container->getCompilerPassConfig()->setOptimizationPasses(array(new ResolveDefinitionTemplatesPass()));
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();
    }

    protected function getConnectionConfig()
    {
        return array('dbal' => array('connections' => array('default' => array('password' => 'foo'))));
    }
}
