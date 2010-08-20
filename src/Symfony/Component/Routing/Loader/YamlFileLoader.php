<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Resource\FileResource;
use Symfony\Component\Yaml\Yaml;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * YamlFileLoader loads Yaml routing files.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class YamlFileLoader extends FileLoader
{
    /**
     * Loads a Yaml file.
     *
     * @param  string $file A Yaml file path
     *
     * @return RouteCollection A RouteCollection instance
     *
     * @throws \InvalidArgumentException When route can't be parsed
     */
    public function load($file)
    {
        $path = $this->findFile($file);

        $config = $this->loadFile($path);

        $collection = new RouteCollection();
        $collection->addResource(new FileResource($path));

        foreach ($config as $name => $config) {
            if (isset($config['resource'])) {
                $prefix = isset($config['prefix']) ? $config['prefix'] : null;
                $this->currentDir = dirname($path);
                $collection->addCollection($this->import($config['resource']), $prefix);
            } elseif (isset($config['pattern'])) {
                $this->parseRoute($collection, $name, $config, $path);
            } else {
                throw new \InvalidArgumentException(sprintf('Unable to parse the "%s" route.', $name));
            }
        }

        return $collection;
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param  mixed $resource A resource
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource)
    {
        return is_string($resource) && 'yml' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    /**
     * @throws \InvalidArgumentException When config pattern is not defined for the given route
     */
    protected function parseRoute(RouteCollection $collection, $name, $config, $file)
    {
        $defaults = isset($config['defaults']) ? $config['defaults'] : array();
        $requirements = isset($config['requirements']) ? $config['requirements'] : array();
        $options = isset($config['options']) ? $config['options'] : array();

        if (!isset($config['pattern'])) {
            throw new \InvalidArgumentException(sprintf('You must define a "pattern" for the "%s" route.', $name));
        }

        $route = new Route($config['pattern'], $defaults, $requirements, $options);

        $collection->addRoute($name, $route);
    }

    protected function loadFile($file)
    {
        return Yaml::load($file);
    }
}
