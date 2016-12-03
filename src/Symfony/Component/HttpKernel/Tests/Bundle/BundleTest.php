<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionNotValidBundle\ExtensionNotValidBundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionAbsentBundle\ExtensionAbsentBundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\Command\FooCommand;

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

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage must implement Symfony\Component\DependencyInjection\Extension\ExtensionInterface
     */
    public function testGetContainerExtensionWithInvalidClass()
    {
        $bundle = new ExtensionNotValidBundle();
        $bundle->getContainerExtension();
    }

    public function testHttpKernelRegisterCommandsIgnoresCommandsThatAreRegisteredAsServices()
    {
        $container = new ContainerBuilder();
        $container->register('console.command.Symfony_Component_HttpKernel_Tests_Fixtures_ExtensionPresentBundle_Command_FooCommand', 'Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\Command\FooCommand');

        $application = $this->getMock('Symfony\Component\Console\Application');
        // add() is never called when the found command classes are already registered as services
        $application->expects($this->never())->method('add');

        $bundle = new ExtensionPresentBundle();
        $bundle->setContainer($container);
        $bundle->registerCommands($application);
    }
}
