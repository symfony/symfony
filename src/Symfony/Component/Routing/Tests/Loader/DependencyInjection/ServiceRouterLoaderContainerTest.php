<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Loader\DependencyInjection\ServiceRouterLoaderContainer;

class ServiceRouterLoaderContainerTest extends TestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ContainerInterface
     */
    private $serviceLocator;

    /**
     * @var ServiceRouterLoaderContainer
     */
    private $serviceRouterLoaderContainer;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->container = new Container();
        $this->container->set('foo', new \stdClass());

        $this->serviceLocator = new Container();
        $this->serviceLocator->set('bar', new \stdClass());

        $this->serviceRouterLoaderContainer = new ServiceRouterLoaderContainer($this->container, $this->serviceLocator);
    }

    /**
     * @group legacy
     * @expectedDeprecation Registering the routing loader "foo" without tagging it with the "routing.router_loader" tag is deprecated since Symfony 4.3 and will be required in Symfony 5.0.
     */
    public function testGet()
    {
        $this->assertSame($this->container->get('foo'), $this->serviceRouterLoaderContainer->get('foo'));
        $this->assertSame($this->serviceLocator->get('bar'), $this->serviceRouterLoaderContainer->get('bar'));
    }

    public function testHas()
    {
        $this->assertTrue($this->serviceRouterLoaderContainer->has('foo'));
        $this->assertTrue($this->serviceRouterLoaderContainer->has('bar'));
        $this->assertFalse($this->serviceRouterLoaderContainer->has('ccc'));
    }
}
