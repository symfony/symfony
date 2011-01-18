<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class FileLoader extends Loader
{
    protected $locator;
    protected $currentDir;

    public function __construct(FileLocator $locator)
    {
        $this->locator = $locator;
    }

    public function getLocator()
    {
        return $this->locator;
    }

    /**
     * Adds routes from a resource.
     *
     * @param mixed  $resource A Resource
     * @param string $type     The resource type
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function import($resource, $type = null)
    {
        $loader = $this->resolve($resource, $type);

        if ($loader instanceof FileLoader && null !== $this->currentDir) {
            $resource = $this->locator->getAbsolutePath($resource, $this->currentDir);
        }

        return $loader->load($resource, $type);
    }
}
