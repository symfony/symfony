<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

use Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle;
use Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionAbsentBundle\ExtensionAbsentBundle;
use Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionPresentBundle\Command\FooCommand;
use Symfony\Tests\Component\HttpKernel\Fixtures\BundlesBundle\BundlesBundle;

class BundleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestBuildData
     */
    public function testBuild($bundleClass, $expectedExtensionClass)
    {
        $container = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('registerExtension')
        );

        if (false === $expectedExtensionClass) {
            $container->expects($this->never())
                ->method('registerExtension');
        } else {
            $extension = new $expectedExtensionClass();
            $container->expects($this->once())
                ->method('registerExtension')
                ->with($this->isInstanceOf($expectedExtensionClass));
        }

        $bundle = new $bundleClass();
        $bundle->build($container);
    }

    public function getTestBuildData()
    {
        return array(
            array(
                'Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle',
                'Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionPresentBundle\DependencyInjection\ExtensionPresentExtension'
            ),
            array(
                'Symfony\Tests\Component\HttpKernel\Fixtures\ExtensionAbsentBundle\ExtensionAbsentBundle',
                false
            ),
            array(
                'Symfony\Tests\Component\HttpKernel\Fixtures\BundlesBundle\BundlesBundle',
                'Symfony\Tests\Component\HttpKernel\Fixtures\BundlesBundle\DependencyInjection\BundlesExtension'
            )
        );
    }

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
}
