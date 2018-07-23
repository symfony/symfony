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

use Symfony\Component\Routing\Loader\PhpFileLoader;
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

    public function __construct(RouteCollection $collection, PhpFileLoader $loader, string $path, string $file)
    {
        $this->collection = $collection;
        $this->loader = $loader;
        $this->path = $path;
        $this->file = $file;
    }

    /**
     * @return ImportConfigurator
     */
    final public function import($resource, $type = null, $ignoreErrors = false)
    {
        $this->loader->setCurrentDir(dirname($this->path));
        $imported = $this->loader->import($resource, $type, $ignoreErrors, $this->file);
        if (!is_array($imported)) {
            return new ImportConfigurator($this->collection, $imported);
        }

        $mergedCollection = new RouteCollection();
        foreach ($imported as $subCollection) {
            $mergedCollection->addCollection($subCollection);
        }

        return new ImportConfigurator($this->collection, $mergedCollection);
    }

    /**
     * @return CollectionConfigurator
     */
    final public function collection($name = '')
    {
        return new CollectionConfigurator($this->collection, $name);
    }
}
