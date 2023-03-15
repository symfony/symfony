<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

/**
 * LoaderResolver selects a loader for a given resource.
 *
 * A resource can be anything (e.g. a full path to a config file or a Closure).
 * Each loader determines whether it can load a resource and how.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LoaderResolver implements LoaderResolverInterface
{
    /**
     * @var LoaderInterface[] An array of LoaderInterface objects
     */
    private array $loaders = [];

    /**
     * @param LoaderInterface[] $loaders An array of loaders
     */
    public function __construct(array $loaders = [])
    {
        foreach ($loaders as $loader) {
            $this->addLoader($loader);
        }
    }

    public function resolve(mixed $resource, string $type = null): LoaderInterface|false
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($resource, $type)) {
                return $loader;
            }
        }

        return false;
    }

    /**
     * @return void
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
        $loader->setResolver($this);
    }

    /**
     * Returns the registered loaders.
     *
     * @return LoaderInterface[]
     */
    public function getLoaders(): array
    {
        return $this->loaders;
    }
}
