<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\Command;

use Symfony\Bundle\AsseticBundle\Command\DumpCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class DumpCommandTest extends \PHPUnit_Framework_TestCase
{
    private $application;
    private $definition;
    private $kernel;
    private $container;
    private $am;

    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }

        $this->application = $this->getMockBuilder('Symfony\\Bundle\\FrameworkBundle\\Console\\Application')
            ->disableOriginalConstructor()
            ->getMock();
        $this->definition = $this->getMockBuilder('Symfony\\Component\\Console\\Input\\InputDefinition')
            ->disableOriginalConstructor()
            ->getMock();
        $this->kernel = $this->getMock('Symfony\\Component\\HttpKernel\\KernelInterface');
        $this->container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface');
        $this->am = $this->getMockBuilder('Assetic\\Factory\\LazyAssetManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->application->expects($this->any())
            ->method('getDefinition')
            ->will($this->returnValue($this->definition));
        $this->definition->expects($this->any())
            ->method('getArguments')
            ->will($this->returnValue(array()));
        $this->definition->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue(array()));
        $this->application->expects($this->any())
            ->method('getKernel')
            ->will($this->returnValue($this->kernel));
        $this->kernel->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($this->container));
        $this->container->expects($this->once())
            ->method('get')
            ->with('assetic.asset_manager')
            ->will($this->returnValue($this->am));

        $this->command = new DumpCommand();
        $this->command->setApplication($this->application);
    }

    public function testEmptyAssetManager()
    {
        $this->am->expects($this->once())
            ->method('getNames')
            ->will($this->returnValue(array()));

        $this->command->run(new ArrayInput(array()), new NullOutput());
    }
}
