<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionAbsentBundle\ExtensionAbsentBundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\Command\FooCommand;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle;

class BundleTest extends \PHPUnit_Framework_TestCase
{
    public function testRegisterCommands()
    {
        $cmd = new FooCommand();
        $app = $this->getMock('Symfony\Component\Console\Application');
        $app->expects($this->once())->method('add')->with($this->equalTo($cmd));

        $bundle = new ExtensionPresentBundle();
        $bundle->registerCommands($app);

        $bundle2 = new ExtensionAbsentBundle();

        $this->assertNull($bundle2->registerCommands($app));
    }

    public function testRegisterCommandsIngoreCommandAsAService()
    {
        $container = new ContainerBuilder();
        $commandClass = 'Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\Command\FooCommand';
        $definition = new Definition($commandClass);
        $definition->addTag('console.command');
        $container->setDefinition('my-command', $definition);
        $container->setAlias('console.command.'.strtolower(str_replace('\\', '_', $commandClass)), 'my-command');
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
