<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AddConsoleCommandPassTest extends TestCase
{
    /**
     * @dataProvider visibilityProvider
     */
    public function testProcess($public)
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->addCompilerPass(new AddConsoleCommandPass());
        $container->setParameter('my-command.class', 'Symfony\Component\Console\Tests\DependencyInjection\MyCommand');

        $definition = new Definition('%my-command.class%');
        $definition->setPublic($public);
        $definition->addTag('console.command');
        $container->setDefinition('my-command', $definition);

        $container->compile();

        $id = $public ? 'my-command' : 'console.command.symfony_component_console_tests_dependencyinjection_mycommand';
        $this->assertTrue($container->hasParameter('console.command.ids'));
        $this->assertSame(array($id), $container->getParameter('console.command.ids'));
    }

    public function visibilityProvider()
    {
        return array(
            array(true),
            array(false),
        );
    }

    public function testProcessSkipAbstractDefinitions()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->addCompilerPass(new AddConsoleCommandPass());

        $definition = new Definition('Symfony\Component\Console\Tests\DependencyInjection\MyCommand');
        $definition->addTag('console.command');
        $definition->setAbstract(true);
        $container->setDefinition('my-command', $definition);

        $container->compile();

        $this->assertSame(array(), $container->getParameter('console.command.ids'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The service "my-command" tagged "console.command" must be a subclass of "Symfony\Component\Console\Command\Command".
     */
    public function testProcessThrowAnExceptionIfTheServiceIsNotASubclassOfCommand()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $container->addCompilerPass(new AddConsoleCommandPass());

        $definition = new Definition('SplObjectStorage');
        $definition->addTag('console.command');
        $container->setDefinition('my-command', $definition);

        $container->compile();
    }
}

class MyCommand extends Command
{
}

class ExtensionPresentBundle extends Bundle
{
}
