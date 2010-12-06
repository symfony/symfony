<?php

namespace Symfony\Component\Routing\Loader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoaderResolver selects a loader for a given resource.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LoaderResolver implements LoaderResolverInterface
{
    protected $loaders;

    /**
     * Constructor.
     *
     * @param array $loaders An array of LoaderInterface instances to add
     */
    public function __construct(array $loaders = array())
    {
        $this->loaders = array();
        foreach ($loaders as $loader) {
            $this->addLoader($loader);
        }
    }

    /**
     * Returns a loader able to load the resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return LoaderInterface|false A LoaderInterface instance supporting the resource if one exists, false otherwise
     */
    public function resolve($resource, $type = null)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($resource, $type)) {
                return $loader;
            }
        }

        return false;
    }

    /**
     * Adds a loader.
     *
     * @param LoaderInterface $loader A LoaderInterface instance
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
        $loader->setResolver($this);
    }

    /**
     * Returns the registered loaders.
     *
     * @return array An array of LoaderInterface instances
     */
    public function getLoaders()
    {
        return $this->loaders;
    }
}
