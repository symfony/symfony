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
use Symfony\Bundle\DoctrineBundle\Command\DoctrineCommand;

class DoctrineCommandTest extends TestCase
{
    private $em;

    public function testSetApplicationEntityManager()
    {
        $kernel = $this->createKernelMock('test');

        $application = new Application($kernel);
        DoctrineCommand::setApplicationEntityManager($application, 'test');

        $this->assertTrue($application->getHelperSet()->has('em'));
        $this->assertTrue($application->getHelperSet()->has('db'));
    }

    public function testSetApplicationConnection()
    {
        $kernel = $this->createKernelMock('test');

        $application = new Application($kernel);
        DoctrineCommand::setApplicationConnection($application, 'test');

        $this->assertFalse($application->getHelperSet()->has('em'));
        $this->assertTrue($application->getHelperSet()->has('db'));
    }

    public function createKernelMock($name)
    {
        $this->em = $this->createTestEntityManager();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $container = new \Symfony\Component\DependencyInjection\Container();
        $container->set(sprintf('doctrine.orm.%s_entity_manager', $name), $this->em);
        $container->set(sprintf('doctrine.dbal.%s_connection', $name), $this->em->getConnection());
        $kernel->expects($this->once())->method('getContainer')->will($this->returnValue($container));
        $kernel->expects($this->once())->method('getBundles')->will($this->returnValue(array()));

        return $kernel;
    }
}