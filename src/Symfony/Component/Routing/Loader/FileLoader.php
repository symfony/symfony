<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class FileLoader extends Loader
{
    protected $currentDir;
    protected $paths;

    /**
     * Constructor.
     *
     * @param string|array $paths A path or an array of paths where to look for resources
     */
    public function __construct($paths = array())
    {
        if (!is_array($paths)) {
            $paths = array($paths);
        }
        $this->paths = $paths;
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
            $resource = $this->getAbsolutePath($resource, $this->currentDir);
        }

        return $loader->load($resource, $type);
    }

    /**
     * Returns a full path for a given file.
     *
     * @param string $file A file path
     *
     * @return string The full path for the file
     *
     * @throws \InvalidArgumentException When file is not found
     */
    protected function findFile($file)
    {
        $path = $this->getAbsolutePath($file);
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist (in: %s).', $file, implode(', ', $this->paths)));
        }

        return $path;
    }

    /**
     * Gets the absolute path for the file path if possible.
     *
     * @param string $file        A file path
     * @param string $currentPath The current path
     *
     * @return string
     */
    protected function getAbsolutePath($file, $currentPath = null)
    {
        if (self::isAbsolutePath($file)) {
            return $file;
        } else if (null !== $currentPath && file_exists($currentPath.DIRECTORY_SEPARATOR.$file)) {
            return $currentPath.DIRECTORY_SEPARATOR.$file;
        } else {
            foreach ($this->paths as $path) {
                if (file_exists($path.DIRECTORY_SEPARATOR.$file)) {
                    return $path.DIRECTORY_SEPARATOR.$file;
                }
            }
        }

        return $file;
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @param string $file A file path
     *
     * @return boolean
     */
    static protected function isAbsolutePath($file)
    {
        if ($file[0] == '/' || $file[0] == '\\' 
            || (strlen($file) > 3 && ctype_alpha($file[0]) 
                && $file[1] == ':' 
                && ($file[2] == '\\' || $file[2] == '/')
            )
        ) {
            return true;
        }

        return false;
    }
}
