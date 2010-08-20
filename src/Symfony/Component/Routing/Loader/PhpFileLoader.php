<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\Resource\FileResource;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * PhpFileLoader loads routes from a PHP file.
 *
 * The file must return a RouteCollection instance.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PhpFileLoader extends FileLoader
{
    /**
     * Loads an array of PHP files.
     *
     * @param mixed $resource The resource
     */
    public function load($file)
    {
        $loader = $this;

        $path = $this->findFile($file);

        $collection = include $path;
        $this->currentDir = dirname($path);
        $collection->addResource(new FileResource($path));

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
        return is_string($resource) && 'php' === pathinfo($resource, PATHINFO_EXTENSION) && is_file($this->getAbsolutePath($resource));
    }
}
