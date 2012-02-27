<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\DataFixtures;

use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Tests\Bridge\Doctrine\Fixtures\ContainerAwareFixture;

class ContainerAwareLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Doctrine\Common\DataFixtures\Loader')) {
            $this->markTestSkipped('Doctrine Data Fixtures is not available.');
        }
    }

    public function testShouldSetContainerOnContainerAwareFixture()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $loader    = new ContainerAwareLoader($container);
        $fixture   = new ContainerAwareFixture();

        $loader->addFixture($fixture);

        $this->assertSame($container, $fixture->container);
    }
}
