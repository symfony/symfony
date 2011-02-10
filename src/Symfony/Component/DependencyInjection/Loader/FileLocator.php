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
 * FileLocator uses an array of pre-defined paths to find files.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FileLocator
{
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
     * Returns a full path for a given file.
     *
     * @param string $file        A file path
     * @param string $currentPath The current path
     *
     * @return string The full path for the file
     *
     * @throws \InvalidArgumentException When file is not found
     */
    public function locate($file, $currentPath = null)
    {
        $path = $this->getAbsolutePath($file, $currentPath);
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
    public function getAbsolutePath($file, $currentPath = null)
    {
        if ($this->isAbsolutePath($file)) {
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
     * @return Boolean
     */
    public function isAbsolutePath($file)
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
