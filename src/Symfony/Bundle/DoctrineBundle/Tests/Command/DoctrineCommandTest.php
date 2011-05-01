<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\DoctrineBundle\Tests\Command;

use Symfony\Bundle\DoctrineBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\DependencyInjection\Container;

class DoctrineCommandTest extends TestCase
{
    private $em;

    public function testSetApplicationEntityManager()
    {
        $application = $this->getApplication();
        $command = $this->getCommand($application);
        $command->setApplicationEntityManager('test');

        $this->assertTrue($application->getHelperSet()->has('em'));
        $this->assertTrue($application->getHelperSet()->has('db'));
    }

    public function testSetApplicationConnection()
    {
        $application = $this->getApplication();
        $command = $this->getCommand($application);
        $command->setApplicationConnection('test');

        $this->assertFalse($application->getHelperSet()->has('em'));
        $this->assertTrue($application->getHelperSet()->has('db'));
    }

    protected function getApplication()
    {
        return new Application($this->createKernelMock('test'));
    }

    protected function getCommand($application)
    {
        $command = $this->getMockForAbstractClass('Symfony\Bundle\DoctrineBundle\Command\DoctrineCommand', array(), '', false);
        $command->setApplication($application);
        $r = new \ReflectionObject($command);
        $p = $r->getProperty('container');
        $p->setAccessible(true);
        $p->setValue($command, $application->getKernel()->getContainer());

        return $command;
    }

    protected function createKernelMock($name)
    {
        $this->em = $this->createTestEntityManager();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $container = new Container();
        $container->set(sprintf('doctrine.orm.%s_entity_manager', $name), $this->em);
        $container->set(sprintf('doctrine.dbal.%s_connection', $name), $this->em->getConnection());
        $container->setParameter('doctrine.orm.entity_managers', array($name => sprintf('doctrine.orm.%s_entity_manager', $name)));
        $container->setParameter('doctrine.dbal.connections', array($name => sprintf('doctrine.dbal.%s_connection', $name)));
        $kernel->expects($this->any())->method('getContainer')->will($this->returnValue($container));
        $kernel->expects($this->any())->method('getBundles')->will($this->returnValue(array()));

        return $kernel;
    }
}
