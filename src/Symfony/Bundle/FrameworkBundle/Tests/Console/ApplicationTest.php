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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

class ApplicationTest extends TestCase
{
    public function testBundleInterfaceImplementation()
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\BundleInterface')->getMock();

        $kernel = $this->getKernel(array($bundle), true);

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(array('list')), new NullOutput());
    }

    public function testBundleCommandsAreRegistered()
    {
        $bundle = $this->createBundleMock(array());

        $kernel = $this->getKernel(array($bundle), true);

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(array('list')), new NullOutput());

        // Calling twice: registration should only be done once.
        $application->doRun(new ArrayInput(array('list')), new NullOutput());
    }

    public function testBundleCommandsAreRetrievable()
    {
        $bundle = $this->createBundleMock(array());

        $kernel = $this->getKernel(array($bundle));

        $application = new Application($kernel);
        $application->all();

        // Calling twice: registration should only be done once.
        $application->all();
    }

    public function testBundleSingleCommandIsRetrievable()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock(array($command));

        $kernel = $this->getKernel(array($bundle));

        $application = new Application($kernel);

        $this->assertSame($command, $application->get('example'));
    }

    public function testBundleCommandCanBeFound()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock(array($command));

        $kernel = $this->getKernel(array($bundle));

        $application = new Application($kernel);

        $this->assertSame($command, $application->find('example'));
    }

    public function testBundleCommandCanBeFoundByAlias()
    {
        $command = new Command('example');
        $command->setAliases(array('alias'));

        $bundle = $this->createBundleMock(array($command));

        $kernel = $this->getKernel(array($bundle));

        $application = new Application($kernel);

        $this->assertSame($command, $application->find('alias'));
    }

    public function testBundleCommandsHaveRightContainer()
    {
        $command = $this->getMockForAbstractClass('Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand', array('foo'), '', true, true, true, array('setContainer'));
        $command->setCode(function () {});
        $command->expects($this->exactly(2))->method('setContainer');

        $application = new Application($this->getKernel(array(), true));
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add($command);
        $tester = new ApplicationTester($application);

        // set container is called here
        $tester->run(array('command' => 'foo'));

        // as the container might have change between two runs, setContainer must called again
        $tester->run(array('command' => 'foo'));
    }

    public function testBundleCommandCanOverriddeAPreExistingCommandWithTheSameName()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock(array($command));

        $kernel = $this->getKernel(array($bundle));

        $application = new Application($kernel);
        $newCommand = new Command('example');
        $application->add($newCommand);

        $this->assertSame($newCommand, $application->get('example'));
    }

    public function testRunOnlyWarnsOnUnregistrableCommand()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register(ThrowingCommand::class, ThrowingCommand::class);
        $container->setParameter('console.command.ids', array(ThrowingCommand::class => ThrowingCommand::class));

        $kernel = $this->getMockBuilder(KernelInterface::class)->getMock();
        $kernel
            ->method('getBundles')
            ->willReturn(array($this->createBundleMock(
                array((new Command('fine'))->setCode(function (InputInterface $input, OutputInterface $output) { $output->write('fine'); }))
            )));
        $kernel
            ->method('getContainer')
            ->willReturn($container);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'fine'));
        $output = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertContains('Some commands could not be registered:', $output);
        $this->assertContains('throwing', $output);
        $this->assertContains('fine', $output);
    }

    public function testRegistrationErrorsAreDisplayedOnCommandNotFound()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);

        $kernel = $this->getMockBuilder(KernelInterface::class)->getMock();
        $kernel
            ->method('getBundles')
            ->willReturn(array($this->createBundleMock(
                array((new Command(null))->setCode(function (InputInterface $input, OutputInterface $output) { $output->write('fine'); }))
            )));
        $kernel
            ->method('getContainer')
            ->willReturn($container);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'fine'));
        $output = $tester->getDisplay();

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertContains('Some commands could not be registered:', $output);
        $this->assertContains('Command "fine" is not defined.', $output);
    }

    private function getKernel(array $bundles, $useDispatcher = false)
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();

        if ($useDispatcher) {
            $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
            $dispatcher
                ->expects($this->atLeastOnce())
                ->method('dispatch')
            ;
            $container
                ->expects($this->atLeastOnce())
                ->method('get')
                ->with($this->equalTo('event_dispatcher'))
                ->will($this->returnValue($dispatcher));
        }

        $container
            ->expects($this->exactly(2))
            ->method('hasParameter')
            ->withConsecutive(array('console.command.ids'), array('console.lazy_command.ids'))
            ->willReturnOnConsecutiveCalls(true, true)
        ;
        $container
            ->expects($this->exactly(2))
            ->method('getParameter')
            ->withConsecutive(array('console.lazy_command.ids'), array('console.command.ids'))
            ->willReturnOnConsecutiveCalls(array(), array())
        ;

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
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

    private function createBundleMock(array $commands)
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\Bundle')->getMock();
        $bundle
            ->expects($this->once())
            ->method('registerCommands')
            ->will($this->returnCallback(function (Application $application) use ($commands) {
                $application->addCommands($commands);
            }))
        ;

        return $bundle;
    }
}

class ThrowingCommand extends Command
{
    public function __construct()
    {
        throw new \Exception('throwing');
    }
}
