<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Config\Resource\DirectoryResource;

/**
 * AnnotationGlobLoader loads routing information from annotations set
 * on PHP classes and methods.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AnnotationGlobLoader extends AnnotationFileLoader
{
    /**
     * Loads from annotations from a directory glob pattern.
     *
     * @param array $paths an array of directories matching pattern
     * @param string $type The resource type
     *
     * @return RouteCollection A RouteCollection instance
     *
     * @throws \InvalidArgumentException When route can't be parsed
     */
    public function load($paths, $type = null)
    {
        $collection = new RouteCollection();
        foreach ($paths as $path) {
            $collection->addResource(new DirectoryResource($path, '/\.php$/'));
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
                if (!$file->isFile() || '.php' !== substr($file->getFilename(), -4)) {
                    continue;
                }

                if ($class = $this->findClass($file)) {
                    $collection->addCollection($this->loader->load($class, $type));
                }
            }
        }

        return $collection;
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return Boolean True if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && false !== strpos($resource, '*') && (!$type || 'annotation' === $type);
    }
}
