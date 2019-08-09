<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Routing\LegacyRouteLoaderContainer;
use Symfony\Component\DependencyInjection\Container;

/**
 * @group legacy
 */
class LegacyRouteLoaderContainerTest extends TestCase
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
     * @var LegacyRouteLoaderContainer
     */
    private $legacyRouteLoaderContainer;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->set('foo', new \stdClass());

        $this->serviceLocator = new Container();
        $this->serviceLocator->set('bar', new \stdClass());

        $this->legacyRouteLoaderContainer = new LegacyRouteLoaderContainer($this->container, $this->serviceLocator);
    }

    /**
     * @expectedDeprecation Registering the service route loader "foo" without tagging it with the "routing.route_loader" tag is deprecated since Symfony 4.4 and will be required in Symfony 5.0.
     */
    public function testGet()
    {
        $this->assertSame($this->container->get('foo'), $this->legacyRouteLoaderContainer->get('foo'));
        $this->assertSame($this->serviceLocator->get('bar'), $this->legacyRouteLoaderContainer->get('bar'));
    }

    public function testHas()
    {
        $this->assertTrue($this->legacyRouteLoaderContainer->has('foo'));
        $this->assertTrue($this->legacyRouteLoaderContainer->has('bar'));
        $this->assertFalse($this->legacyRouteLoaderContainer->has('ccc'));
    }
}
