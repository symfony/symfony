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
use Symfony\Bundle\FrameworkBundle\Tests\Console\Fixtures\FooCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Console\ConsoleEvents;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class ApplicationTest extends TestCase
{
    public function testBundleInterfaceImplementation()
    {
        $bundle = $this->getMock("Symfony\Component\HttpKernel\Bundle\BundleInterface");

        $kernel = $this->getKernel(array($bundle), $this->never());

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(array('list')), new NullOutput());
    }

    public function testBundleCommandsAreRegistered()
    {
        $bundle = $this->getMock("Symfony\Component\HttpKernel\Bundle\Bundle");
        $bundle->expects($this->once())->method('registerCommands');

        $kernel = $this->getKernel(array($bundle), $this->never());

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(array('list')), new NullOutput());
    }

    public function testCommandDispatchEvents()
    {
        $kernel = $this->getKernel(array(), $this->once());

        $application = new Application($kernel);
        $application->add(new FooCommand('foo'));

        $application->doRun(new ArrayInput(array('foo')), new NullOutput());
    }

    private function getKernel(array $bundles, $dispatcherExpected = null)
    {
        $kernel = $this->getMock("Symfony\Component\HttpKernel\KernelInterface");
        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->will($this->returnValue($bundles))
        ;
        
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $dispatcherExpected = $dispatcherExpected ?: $this->any();
        if ($this->never() == $dispatcherExpected) {
            $container
                ->expects($dispatcherExpected)
                ->method('get');
        } else {
            $eventDispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
            $eventDispatcher
                ->expects($this->at(0))
                ->method('dispatch')
                ->with(
                    $this->equalTo(ConsoleEvents::INIT),
                    $this->isInstanceOf('Symfony\Bundle\FrameworkBundle\Event\ConsoleEvent')
                );
            $eventDispatcher
                ->expects($this->at(1))
                ->method('dispatch')
                ->with(
                    $this->equalTo(ConsoleEvents::TERMINATE),
                    $this->isInstanceOf('Symfony\Bundle\FrameworkBundle\Event\ConsoleTerminateEvent')
                );
            $container
                ->expects($dispatcherExpected)
                ->method('get')
                ->with($this->equalTo('event_dispatcher'))
                ->will($this->returnValue($eventDispatcher));
        }

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($container))
        ;

        return $kernel;
    }
}
