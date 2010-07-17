<?php

namespace Symfony\Components\Routing\Loader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoaderResolver selects a loader for a given resource..
 *
 * @package    Symfony
 * @subpackage Components_Routing
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LoaderResolver implements LoaderResolverInterface
{
    protected $loaders;

    /**
     * Constructor.
     *
     * @param \Symfony\Components\Routing\Loader\LoaderInterface[] $loaders An array of loaders
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
     *
     * @return Symfony\Components\Routing\Loader\LoaderInterface A LoaderInterface instance
     */
    public function resolve($resource)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($resource)) {
                return $loader;
            }
        }

        return false;
    }

    /**
     * Sets a loader.
     *
     * @param \Symfony\Components\Routing\Loader\LoaderInterface $loader A LoaderInterface instance
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
        $loader->setResolver($this);
    }

    /**
     * Returns the registered loaders.
     *
     * @return \Symfony\Components\Routing\Loader\LoaderInterface[] A array of LoaderInterface instances
     */
    public function getLoaders()
    {
        return $this->loaders;
    }
}
