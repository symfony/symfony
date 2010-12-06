<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;
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
 * AnnotationFileLoader loads routing information from annotations set
 * on a PHP class and its methods.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AnnotationFileLoader extends FileLoader
{
    protected $loader;

    /**
     * Constructor.
     *
     * @param AnnotationClassLoader $loader An AnnotationClassLoader instance
     * @param string|array          $paths  A path or an array of paths where to look for resources
     */
    public function __construct(AnnotationClassLoader $loader, $paths = array())
    {
        if (!function_exists('token_get_all')) {
            throw new \RuntimeException('The Tokenizer extension is required for the routing annotation loaders.');
        }

        parent::__construct($paths);

        $this->loader = $loader;
    }

    /**
     * Loads from annotations from a file.
     *
     * @param string $file A PHP file path
     * @param string $type The resource type
     *
     * @return RouteCollection A RouteCollection instance
     *
     * @throws \InvalidArgumentException When the file does not exist or its routes cannot be parsed
     */
    public function load($file, $type = null)
    {
        $path = $this->getAbsolutePath($file);
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" cannot be found (in: %s).', $file, implode(', ', $this->paths)));
        }

        $collection = new RouteCollection();
        if ($class = $this->findClass($path)) {
            $collection->addResource(new FileResource($path));
            $collection->addCollection($this->loader->load($class, $type));
        }

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
        return is_string($resource) && 'php' === pathinfo($resource, PATHINFO_EXTENSION) && (!$type || 'annotation' === $type);
    }

    /**
     * Returns the full class name for the first class in the file.
     *
     * @param string $file A PHP file path
     *
     * @return string|false Full class name if found, false otherwise 
     */
    protected function findClass($file)
    {
        $class = false;
        $namespace = false;
        $tokens = token_get_all(file_get_contents($file));
        while ($token = array_shift($tokens)) {
            if (!is_array($token)) {
                continue;
            }

            if (true === $class && T_STRING === $token[0]) {
                return $namespace.'\\'.$token[1];
            }

            if (true === $namespace && T_STRING === $token[0]) {
                $namespace = '';
                do {
                    $namespace .= $token[1];
                    $token = array_shift($tokens);
                } while ($tokens && is_array($token) && in_array($token[0], array(T_NS_SEPARATOR, T_STRING)));
            }

            if (T_CLASS === $token[0]) {
                $class = true;
            }

            if (T_NAMESPACE === $token[0]) {
                $namespace = true;
            }
        }

        return false;
    }
}
