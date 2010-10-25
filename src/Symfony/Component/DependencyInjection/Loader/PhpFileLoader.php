<?php

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\Resource\FileResource;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * PhpFileLoader loads service definitions from a PHP file.
 *
 * The PHP file is required and the $container variable can be
 * used form the file to change the container.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
        $container = $this->container;
        $loader = $this;

        $path = $this->findFile($file);
        $this->currentDir = dirname($path);
        $this->container->addResource(new FileResource($path));

        include $path;
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
        return is_string($resource) && 'php' === pathinfo($resource, PATHINFO_EXTENSION);
    }
}
