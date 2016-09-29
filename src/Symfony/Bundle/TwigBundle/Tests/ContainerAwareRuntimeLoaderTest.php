<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\TwigBundle\ContainerAwareRuntimeLoader;

class ContainerAwareRuntimeLoaderTest extends TestCase
{
    public function testLoad()
    {
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $container->expects($this->once())->method('get')->with('foo');

        $loader = new ContainerAwareRuntimeLoader($container, array(
            'FooClass' => 'foo',
        ));
        $loader->load('FooClass');
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Class "BarClass" is not configured as a Twig runtime.
     */
    public function testLoadWithoutAMatch()
    {
        $loader = new ContainerAwareRuntimeLoader($this->getMockBuilder(ContainerInterface::class)->getMock(), array());
        $loader->load('BarClass');
    }
}
