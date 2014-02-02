<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DataFixtures;

use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ContainerAwareFixture;

class ContainerAwareLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldSetContainerOnContainerAwareFixture()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $loader    = new ContainerAwareLoader($container);
        $fixture   = new ContainerAwareFixture();

        $loader->addFixture($fixture);

        $this->assertSame($container, $fixture->container);
    }
}
