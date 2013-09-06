<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\ApplicationTester;

class ApplicationTest extends TestCase
{
    public function testBundleInterfaceImplementation()
    {
        $bundle = $this->getMock('Symfony\Component\HttpKernel\Bundle\BundleInterface');

        $kernel = $this->getKernel(array($bundle));

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(array('list')), new NullOutput());
    }

    public function testBundleCommandsAreRegistered()
    {
        $bundle = $this->getMock('Symfony\Component\HttpKernel\Bundle\Bundle');
        $bundle->expects($this->once())->method('registerCommands');

        $kernel = $this->getKernel(array($bundle));

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(array('list')), new NullOutput());
    }

    public function testBundleCommandsHaveRightContainer()
    {
        $command = $this->getMockForAbstractClass('Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand', array('foo'), '', true, true, true, array('setContainer'));
        $command->setCode(function () {});
        $command->expects($this->exactly(2))->method('setContainer');

        $application = new Application($this->getKernel(array()));
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add($command);
        $tester = new ApplicationTester($application);

        // set container is called here
        $tester->run(array('command' => 'foo'));

        // as the container might have change between two runs, setContainer must called again
        $tester->run(array('command' => 'foo'));
    }

    private function getKernel(array $bundles)
    {
        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $dispatcher
            ->expects($this->atLeastOnce())
            ->method('dispatch')
        ;

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with($this->equalTo('event_dispatcher'))
            ->will($this->returnValue($dispatcher))
        ;

        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->will($this->returnValue($bundles))
        ;
        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($container))
        ;

        return $kernel;
    }
}
