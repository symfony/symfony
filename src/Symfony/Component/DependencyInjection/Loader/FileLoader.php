<?php

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;

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
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param string|array     $paths A path or an array of paths where to look for resources
     */
    public function __construct(ContainerBuilder $container, $paths = array())
    {
        parent::__construct($container);

        if (!is_array($paths)) {
            $paths = array($paths);
        }

        $this->paths = $paths;
    }

    /**
     * Adds definitions and parameters from a resource.
     *
     * @param mixed $resource A Resource
     */
    public function import($resource, $ignoreErrors = false)
    {
        try {
            $loader = $this->resolve($resource);

            if ($loader instanceof FileLoader && null !== $this->currentDir) {
                $resource = $this->getAbsolutePath($resource, $this->currentDir);
            }

            $loader->load($resource);
        } catch (\Exception $e) {
            if (!$ignoreErrors) {
                throw $e;
            }
        }
    }

    /**
     * @throws \InvalidArgumentException When provided file does not exist
     */
    protected function findFile($file)
    {
        $path = $this->getAbsolutePath($file);
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist (in: %s).', $file, implode(', ', $this->paths)));
        }

        return $path;
    }

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

    static protected function isAbsolutePath($file)
    {
        return
            '/' == $file[0]
            ||
            '\\' == $file[0]
            ||
            (
                3 < strlen($file)
                &&
                ctype_alpha($file[0])
                &&
                ':' == $file[1]
                &&
                (
                    '\\' == $file[2]
                    ||
                    '/' == $file[2]
                )
            )
        ;
    }
}
