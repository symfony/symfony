<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

/**
 * LoaderResolver selects a loader for a given resource.
 *
 * A resource can be anything that can be converted to a ContainerBuilder
 * instance (e.g. a full path to a config file or a Closure). Each
 * loader determines whether it can load a resource and how.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LoaderResolver implements LoaderResolverInterface
{
    /**
     * @var LoaderInterface[] An array of LoaderInterface objects
     */
    protected $loaders;

    /**
     * Constructor.
     *
     * @param LoaderInterface[] $loaders An array of loaders
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
     * @return LoaderInterface|false A LoaderInterface instance
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
     * @return LoaderInterface[] An array of LoaderInterface instances
     */
    public function getLoaders()
    {
        return $this->loaders;
    }
}
