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

use Symfony\Component\HttpKernel\Bundle\Bundle;

use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionAbsentBundle\ExtensionAbsentBundle;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\Command\FooCommand;

class BundleTest extends \PHPUnit_Framework_TestCase
{
    public function testRegisterCommands()
    {
        if (!class_exists('Symfony\Component\Console\Application')) {
            $this->markTestSkipped('The "Console" component is not available');
        }

        if (!interface_exists('Symfony\Component\DependencyInjection\ContainerAwareInterface')) {
            $this->markTestSkipped('The "DependencyInjection" component is not available');
        }

        if (!class_exists('Symfony\Component\Finder\Finder')) {
            $this->markTestSkipped('The "Finder" component is not available');
        }

        $cmd = new FooCommand();
        $app = $this->getMock('Symfony\Component\Console\Application');
        $app->expects($this->once())->method('add')->with($this->equalTo($cmd));

        $bundle = new ExtensionPresentBundle();
        $bundle->registerCommands($app);

        $bundle2 = new ExtensionAbsentBundle();

        $this->assertNull($bundle2->registerCommands($app));
    }
}
