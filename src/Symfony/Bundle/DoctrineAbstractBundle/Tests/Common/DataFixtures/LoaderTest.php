<?php

namespace Symfony\Bundle\DoctrineAbstractBundle\Tests\Common\DataFixtures;

use Symfony\Bundle\DoctrineAbstractBundle\Tests\TestCase;
use Symfony\Bundle\DoctrineAbstractBundle\Tests\Common\ContainerAwareFixture;
use Symfony\Bundle\DoctrineAbstractBundle\Common\DataFixtures\Loader;

class LoaderTest extends TestCase
{
    public function testShouldSetContainerOnContainerAwareFixture()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $loader    = new Loader($container);
        $fixture   = new ContainerAwareFixture();

        $loader->addFixture($fixture);

        $this->assertSame($container, $fixture->container);
    }
}
