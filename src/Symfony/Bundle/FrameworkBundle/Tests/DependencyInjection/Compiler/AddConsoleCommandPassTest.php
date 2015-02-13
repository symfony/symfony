<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConsoleCommandPass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AddConsoleCommandPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->setParameter('my-command.class', 'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\MyCommand');

        $definition = new Definition('%my-command.class%');
        $definition->addTag('console.command');
        $container->setDefinition('my-command', $definition);

        $container->compile();

        $alias = 'console.command.symfony_bundle_frameworkbundle_tests_dependencyinjection_compiler_mycommand';
        $this->assertTrue($container->hasAlias($alias));
        $this->assertSame('my-command', (string) $container->getAlias($alias));

        $this->assertTrue($container->hasParameter('console.command.ids'));
        $this->assertSame(array('my-command'), $container->getParameter('console.command.ids'));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The service "my-command" tagged "console.command" must be public.
     */
    public function testProcessThrowAnExceptionIfTheServiceIsNotPublic()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddConsoleCommandPass());

        $definition = new Definition('Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\MyCommand');
        $definition->addTag('console.command');
        $definition->setPublic(false);
        $container->setDefinition('my-command', $definition);

        $container->compile();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The service "my-command" tagged "console.command" must not be abstract.
     */
    public function testProcessThrowAnExceptionIfTheServiceIsAbstract()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddConsoleCommandPass());

        $definition = new Definition('Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\MyCommand');
        $definition->addTag('console.command');
        $definition->setAbstract(true);
        $container->setDefinition('my-command', $definition);

        $container->compile();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The service "my-command" tagged "console.command" must be a subclass of "Symfony\Component\Console\Command\Command".
     */
    public function testProcessThrowAnExceptionIfTheServiceIsNotASubclassOfCommand()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddConsoleCommandPass());

        $definition = new Definition('SplObjectStorage');
        $definition->addTag('console.command');
        $container->setDefinition('my-command', $definition);

        $container->compile();
    }

    public function testHttpKernelRegisterCommandsIngoreCommandAsAService()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddConsoleCommandPass());
        $definition = new Definition('Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\MyCommand');
        $definition->addTag('console.command');
        $container->setDefinition('my-command', $definition);
        $container->compile();

        $application = $this->getMock('Symfony\Component\Console\Application');
        // Never called, because it's the
        // Symfony\Bundle\FrameworkBundle\Console\Application that register
        // commands as a service
        $application->expects($this->never())->method('add');

        $bundle = new ExtensionPresentBundle();
        $bundle->setContainer($container);
        $bundle->registerCommands($application);
    }
}

class MyCommand extends Command
{
}

class ExtensionPresentBundle extends Bundle
{
}
