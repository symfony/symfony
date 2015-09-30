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
use Symfony\Component\Routing\RouteCollection;

/**
 * A route loader that calls a method on an object to load the routes.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
abstract class ObjectRouteLoader extends Loader
{
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
        $routeLoader = $this->getRouteLoaderService($resource);

        if (!$routeLoader instanceof RouteLoaderInterface) {
            throw new \LogicException(sprintf('Service "%s" must implement RouteLoaderInterface.', $resource));
        }

        $routeCollection = $routeLoader->getRouteCollection($this);

        // make the service file tracked so that if it changes, the cache rebuilds
        $this->addClassResource(new \ReflectionClass($routeLoader), $routeCollection);

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

    /**
     * Returns a RouteLoaderInterface object matching the id.
     *
     * For example, if your application uses a service container,
     * the $id may be a service id.
     *
     * @param string $id
     * @return RouteLoaderInterface
     */
    abstract protected function getRouteLoaderService($id);

    private function addClassResource(\ReflectionClass $class, RouteCollection $collection)
    {
        do {
            $collection->addResource(new FileResource($class->getFileName()));
        } while ($class = $class->getParentClass());
    }
}
