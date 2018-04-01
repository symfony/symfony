<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Tests\DataFixtures;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symphony\Bridge\Doctrine\Tests\Fixtures\ContainerAwareFixture;

class ContainerAwareLoaderTest extends TestCase
{
    public function testShouldSetContainerOnContainerAwareFixture()
    {
        $container = $this->getMockBuilder('Symphony\Component\DependencyInjection\ContainerInterface')->getMock();
        $loader = new ContainerAwareLoader($container);
        $fixture = new ContainerAwareFixture();

        $loader->addFixture($fixture);

        $this->assertSame($container, $fixture->container);
    }
}
