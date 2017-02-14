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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\TwigBundle\ContainerAwareRuntimeLoader;

/**
 * @group legacy
 */
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

    public function testLoadWithoutAMatch()
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $logger->expects($this->once())->method('warning')->with('Class "BarClass" is not configured as a Twig runtime. Add the "twig.runtime" tag to the related service in the container.');
        $loader = new ContainerAwareRuntimeLoader($this->getMockBuilder(ContainerInterface::class)->getMock(), array(), $logger);
        $this->assertNull($loader->load('BarClass'));
    }
}
