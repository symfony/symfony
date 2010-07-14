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
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;

class DoctrineExtensionTest extends TestCase
{
    public function testDbalLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new DoctrineExtension(array(), array());

        $configuration = $loader->dbalLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Bundle\\DoctrineBundle\\DataCollector\\DoctrineDataCollector', $configuration->getParameter('doctrine.data_collector.class'), '->dbalLoad() loads the dbal.xml file if not already loaded');

        // doctrine.dbal.default_connection
        $this->assertEquals('default', $configuration->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $configuration = $loader->dbalLoad(array('default_connection' => 'foo'), $configuration);
        $this->assertEquals('foo', $configuration->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $configuration = $loader->dbalLoad(array(), $configuration);
        $this->assertEquals('foo', $configuration->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');

        $configuration = new BuilderConfiguration();
        $loader = new DoctrineExtension(array(), array());
        $configuration = $loader->dbalLoad(array('password' => 'foo'), $configuration);

        $arguments = $configuration->getDefinition('doctrine.dbal.default_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);

        $configuration = $loader->dbalLoad(array('user' => 'foo'), $configuration);
        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);
    }

    public function testDbalLoadFromXmlMultipleConnections()
    {
        $configuration = new BuilderConfiguration();
        $loader = new DoctrineExtension(array(), array());
        XmlFileLoader::registerExtension($loader);

        $loadXml = new XmlFileLoader(__DIR__.'/Fixtures/xml');
        $configuration->merge($loadXml->load('dbal_service_multiple_connections.xml'));
        $configuration = $loader->dbalLoad(array(), $configuration);

        // doctrine.dbal.mysql_connection
        $arguments = $configuration->getDefinition('doctrine.dbal.mysql_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);

        // doctrine.dbal.sqlite_connection
        $arguments = $configuration->getDefinition('doctrine.dbal.sqlite_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('sqlite_s3cr3t', $config['password']);
        $this->assertEquals('sqlite_user', $config['user']);
        $this->assertEquals('sqlite_db', $config['dbname']);
        $this->assertEquals(true, $config['memory']);

        // doctrine.dbal.oci_connection
        $arguments = $configuration->getDefinition('doctrine.dbal.oci_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('oracle_s3cr3t', $config['password']);
        $this->assertEquals('oracle_user', $config['user']);
        $this->assertEquals('oracle_db', $config['dbname']);
        $this->assertEquals('utf8', $config['charset']);
    }


    public function testDbalLoadFromXmlSingleConnection()
    {
        $configuration = new BuilderConfiguration();
        $loader = new DoctrineExtension(array(), array());
        XmlFileLoader::registerExtension($loader);

        $loadXml = new XmlFileLoader(__DIR__.'/Fixtures/xml');
        $configuration->merge($loadXml->load('dbal_service_single_connection.xml'));
        $configuration = $loader->dbalLoad(array(), $configuration);

        // doctrine.dbal.mysql_connection
        $arguments = $configuration->getDefinition('doctrine.dbal.mysql_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('mysql_s3cr3t', $config['password']);
        $this->assertEquals('mysql_user', $config['user']);
        $this->assertEquals('mysql_db', $config['dbname']);
        $this->assertEquals('/path/to/mysqld.sock', $config['unix_socket']);
    }
}
