<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader\Configurator;

use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RoutingConfigurator
{
    use Traits\AddTrait;

    private $loader;
    private $path;
    private $file;

    public function __construct(RouteCollection $collection, LoaderInterface $loader, ?string $path, string $file)
    {
        $this->collection = $collection;
        $this->loader = $loader;
        $this->path = $path;
        $this->file = $file;
    }

    /**
     * @param string|string[]|null $exclude Glob patterns to exclude from the import
     */
    final public function import($resource, string $type = null, bool $ignoreErrors = false, $exclude = null): ImportConfigurator
    {
        $imported = $this->load($resource, $type, $ignoreErrors, $exclude) ?: [];
        if (!\is_array($imported)) {
            return new ImportConfigurator($this->collection, $imported);
        }

        $mergedCollection = new RouteCollection();
        foreach ($imported as $subCollection) {
            $mergedCollection->addCollection($subCollection);
        }

        return new ImportConfigurator($this->collection, $mergedCollection);
    }

    final public function collection(string $name = ''): CollectionConfigurator
    {
        return new CollectionConfigurator($this->collection, $name);
    }

    /**
     * @param string|string[]|null $exclude
     *
     * @return RouteCollection|RouteCollection[]|null
     */
    private function load($resource, ?string $type, bool $ignoreErrors, $exclude)
    {
        $loader = $this->loader;

        if (!$loader->supports($resource, $type)) {
            if (null === $resolver = $loader->getResolver()) {
                throw new LoaderLoadException($resource, $this->file, null, null, $type);
            }

            if (false === $loader = $resolver->resolve($resource, $type)) {
                throw new LoaderLoadException($resource, $this->file, null, null, $type);
            }
        }

        if (!$loader instanceof FileLoader) {
            return $loader->load($resource, $type);
        }

        if (null !== $this->path) {
            $this->loader->setCurrentDir(\dirname($this->path));
        }

        return $this->loader->import($resource, $type, $ignoreErrors, $this->file, $exclude);
    }
}
