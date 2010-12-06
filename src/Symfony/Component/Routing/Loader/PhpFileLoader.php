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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PhpFileLoader extends FileLoader
{
    /**
     * Loads a PHP file.
     *
     * @param mixed  $resource A PHP file path
     * @param string $type     The resource type
     */
    public function load($file, $type = null)
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
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return boolean True if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'php' === pathinfo($resource, PATHINFO_EXTENSION) && (!$type || 'php' === $type);
    }
}
