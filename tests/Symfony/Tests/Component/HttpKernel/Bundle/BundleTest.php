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
}
