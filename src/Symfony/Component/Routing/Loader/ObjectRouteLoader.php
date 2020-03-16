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
     * Returns the object that the method will be called on to load routes.
     *
     * For example, if your application uses a service container,
     * the $id may be a service id.
     *
     * @param string $id
     *
     * @return object
     */
    abstract protected function getServiceObject($id);

    /**
     * Calls the service that will load the routes.
     *
     * @param mixed       $resource Some value that will resolve to a callable
     * @param string|null $type     The resource type
     *
     * @return RouteCollection
     */
    public function load($resource, $type = null)
    {
        $parts = explode(':', $resource);
        if (2 != \count($parts)) {
            throw new \InvalidArgumentException(sprintf('Invalid resource "%s" passed to the "service" route loader: use the format "service_name:methodName".', $resource));
        }

        $serviceString = $parts[0];
        $method = $parts[1];

        $loaderObject = $this->getServiceObject($serviceString);

        if (!\is_object($loaderObject)) {
            throw new \LogicException(sprintf('"%s:getServiceObject()" must return an object: "%s" returned.', static::class, \gettype($loaderObject)));
        }

        if (!method_exists($loaderObject, $method)) {
            throw new \BadMethodCallException(sprintf('Method "%s" not found on "%s" when importing routing resource "%s".', $method, \get_class($loaderObject), $resource));
        }

        $routeCollection = \call_user_func([$loaderObject, $method], $this);

        if (!$routeCollection instanceof RouteCollection) {
            $type = \is_object($routeCollection) ? \get_class($routeCollection) : \gettype($routeCollection);

            throw new \LogicException(sprintf('The "%s"::%s method must return a RouteCollection: "%s" returned.', \get_class($loaderObject), $method, $type));
        }

        // make the service file tracked so that if it changes, the cache rebuilds
        $this->addClassResource(new \ReflectionClass($loaderObject), $routeCollection);

        return $routeCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return 'service' === $type;
    }

    private function addClassResource(\ReflectionClass $class, RouteCollection $collection)
    {
        do {
            if (is_file($class->getFileName())) {
                $collection->addResource(new FileResource($class->getFileName()));
            }
        } while ($class = $class->getParentClass());
    }
}
