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
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;

class DoctrineExtensionTest extends TestCase
{
    public function testDbalLoad()
    {
        $container = new ContainerBuilder();
        $loader = new DoctrineExtension(array(), array(), sys_get_temp_dir());

        $loader->dbalLoad(array(), $container);
        $this->assertEquals('Symfony\\Bundle\\DoctrineBundle\\DataCollector\\DoctrineDataCollector', $container->getParameter('doctrine.data_collector.class'), '->dbalLoad() loads the dbal.xml file if not already loaded');

        // doctrine.dbal.default_connection
        $this->assertEquals('default', $container->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $loader->dbalLoad(array('default_connection' => 'foo'), $container);
        $this->assertEquals('foo', $container->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $loader->dbalLoad(array(), $container);
        $this->assertEquals('foo', $container->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');

        $container = new ContainerBuilder();
        $loader = new DoctrineExtension(array(), array(), sys_get_temp_dir());
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
        $container = new ContainerBuilder();
        $loader = new DoctrineExtension(array(), array(), sys_get_temp_dir());
        $container->registerExtension($loader);

        $loadXml = new XmlFileLoader($container, __DIR__.'/Fixtures/xml');
        $loadXml->load('dbal_service_multiple_connections.xml');
        $loader->dbalLoad(array(), $container);

        // doctrine.dbal.mysql_connection
        $arguments = $container->getDefinition('doctrine.dbal.mysql_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);

        // doctrine.dbal.sqlite_connection
        $arguments = $container->getDefinition('doctrine.dbal.sqlite_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('sqlite_s3cr3t', $config['password']);
        $this->assertEquals('sqlite_user', $config['user']);
        $this->assertEquals('sqlite_db', $config['dbname']);
        $this->assertEquals(true, $config['memory']);

        // doctrine.dbal.oci_connection
        $arguments = $container->getDefinition('doctrine.dbal.oci_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('oracle_s3cr3t', $config['password']);
        $this->assertEquals('oracle_user', $config['user']);
        $this->assertEquals('oracle_db', $config['dbname']);
        $this->assertEquals('utf8', $config['charset']);
    }


    public function testDbalLoadFromXmlSingleConnection()
    {
        $container = new ContainerBuilder();
        $loader = new DoctrineExtension(array(), array(), sys_get_temp_dir());
        $container->registerExtension($loader);

        $loadXml = new XmlFileLoader($container, __DIR__.'/Fixtures/xml');
        $loadXml->load('dbal_service_single_connection.xml');
        $loader->dbalLoad(array(), $container);

        // doctrine.dbal.mysql_connection
        $arguments = $container->getDefinition('doctrine.dbal.mysql_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);
    }
}
