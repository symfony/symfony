<?php

namespace Symfony\Components\Routing\Loader;

use Symfony\Components\Routing\RouteCollection;
use Symfony\Components\Routing\Route;
use Symfony\Components\Routing\Resource\FileResource;
use Symfony\Components\Yaml\Yaml;

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
 * @package    Symfony
 * @subpackage Components_Routing
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
                $this->parseImport($collection, $name, $config, $path);
            } elseif (isset($config['pattern'])) {
                $this->parseRoute($collection, $name, $config, $path);
            } else {
                throw new \InvalidArgumentException(sprintf('Unable to parse the "%s" route.', $name));
            }
        }

        return $collection;
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

    /**
     * @throws \InvalidArgumentException When import resource is not defined
     */
    protected function parseImport(RouteCollection $collection, $name, $import, $file)
    {
        if (!isset($import['resource'])) {
            throw new \InvalidArgumentException(sprintf('You must define a "resource" when importing (%s).', $name));
        }

        $class = null;
        if (isset($import['class']) && $import['class'] !== get_class($this)) {
            $class = $import['class'];
        } else {
            // try to detect loader with the extension
            switch (pathinfo($import['resource'], PATHINFO_EXTENSION)) {
                case 'xml':
                    $class = 'Symfony\\Components\\Routing\\Loader\\XmlFileLoader';
                    break;
            }
        }

        $loader = null === $class ? $this : new $class($this->paths);

        $importedFile = $this->getAbsolutePath($import['resource'], dirname($file));

        $collection->addCollection($loader->load($importedFile), isset($import['prefix']) ? $import['prefix'] : null);
    }

    protected function loadFile($file)
    {
        return Yaml::load($file);
    }
}
