<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Tests;

use Symfony\Bundle\DoctrineBundle\Registry;

class RegistryTest extends TestCase
{
    public function testGetDefaultConnectionName()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultConnectionName());
    }

    public function testGetDefaultEntityManagerName()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->assertEquals('default', $registry->getDefaultEntityManagerName());
    }

    public function testGetDefaultConnection()
    {
        $conn = $this->getMock('Doctrine\DBAL\Connection', array(), array(), '', false);
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.dbal.default_connection'))
                  ->will($this->returnValue($conn));

        $registry = new Registry($container, array('default' => 'doctrine.dbal.default_connection'), array(), 'default', 'default');

        $this->assertSame($conn, $registry->getConnection());
    }

    public function testGetConnection()
    {
        $conn = $this->getMock('Doctrine\DBAL\Connection', array(), array(), '', false);
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.dbal.default_connection'))
                  ->will($this->returnValue($conn));

        $registry = new Registry($container, array('default' => 'doctrine.dbal.default_connection'), array(), 'default', 'default');

        $this->assertSame($conn, $registry->getConnection('default'));
    }

    public function testGetUnknownConnection()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->setExpectedException('InvalidArgumentException', 'Doctrine Connection named "default" does not exist.');
        $registry->getConnection('default');
    }

    public function testGetConnectionNames()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array('default' => 'doctrine.dbal.default_connection'), array(), 'default', 'default');

        $this->assertEquals(array('default' => 'doctrine.dbal.default_connection'), $registry->getConnectionNames());
    }

    public function testGetDefaultEntityManager()
    {
        $em = new \stdClass();
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, array(), array('default' => 'doctrine.orm.default_entity_manager'), 'default', 'default');

        $this->assertSame($em, $registry->getEntityManager());
    }

    public function testGetEntityManager()
    {
        $em = new \stdClass();
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('get')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'))
                  ->will($this->returnValue($em));

        $registry = new Registry($container, array(), array('default' => 'doctrine.orm.default_entity_manager'), 'default', 'default');

        $this->assertSame($em, $registry->getEntityManager('default'));
    }

    public function testGetUnknownEntityManager()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->setExpectedException('InvalidArgumentException', 'Doctrine EntityManager named "default" does not exist.');
        $registry->getEntityManager('default');
    }

    public function testResetDefaultEntityManager()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('set')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'), $this->equalTo(null));

        $registry = new Registry($container, array(), array('default' => 'doctrine.orm.default_entity_manager'), 'default', 'default');
        $registry->resetEntityManager();
    }

    public function testResetEntityManager()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->once())
                  ->method('set')
                  ->with($this->equalTo('doctrine.orm.default_entity_manager'), $this->equalTo(null));

        $registry = new Registry($container, array(), array('default' => 'doctrine.orm.default_entity_manager'), 'default', 'default');
        $registry->resetEntityManager('default');
    }

    public function testResetUnknownEntityManager()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $registry = new Registry($container, array(), array(), 'default', 'default');

        $this->setExpectedException('InvalidArgumentException', 'Doctrine EntityManager named "default" does not exist.');
        $registry->resetEntityManager('default');
    }
}
