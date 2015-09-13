<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * A route loader that executes a service to load the routes.
 *
 * The depends on the DependencyInjection component
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class ServiceRouterLoader extends Loader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Calls the service that will load the routes.
     *
     * @param string      $resource The name of the service to load
     * @param string|null $type     The resource type
     *
     * @return RouteCollection
     */
    public function load($resource, $type = null)
    {
        $service = $this->container->get($resource);

        if (!$service instanceof RouteLoaderInterface) {
            throw new \LogicException(sprintf('Service "%s" must implement RouteLoaderInterface.', $resource));
        }

        $routeCollection = $service->getRouteCollection($this);

        // make the service file tracked so that if it changes, the cache rebuilds
        $this->addClassResource(new \ReflectionClass($service), $routeCollection);

        return $routeCollection;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function supports($resource, $type = null)
    {
        return 'service' === $type;
    }

    private function addClassResource(\ReflectionClass $class, RouteCollection $collection)
    {
        do {
            $collection->addResource(new FileResource($class->getFileName()));
        } while ($class = $class->getParentClass());
    }
}
