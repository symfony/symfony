<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Loader\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symphony\Component\Routing\Loader\ObjectRouteLoader;

/**
 * A route loader that executes a service to load the routes.
 *
 * This depends on the DependencyInjection component.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class ServiceRouterLoader extends ObjectRouteLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getServiceObject($id)
    {
        return $this->container->get($id);
    }
}
