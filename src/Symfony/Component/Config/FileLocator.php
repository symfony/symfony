<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

/**
 * FileLocator uses an array of pre-defined paths to find files.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FileLocator implements FileLocatorInterface
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
     * Returns a full path for a given file name.
     *
     * @param mixed  $name        The file name to locate
     * @param string $currentPath The current path
     *
     * @return string The full path for the file
     *
     * @throws \InvalidArgumentException When file is not found
     */
    public function locate($name, $currentPath = null, $first = true)
    {
        if ($this->isAbsolutePath($name)) {
            if (!file_exists($name)) {
                throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $name));
            }

            return $name;
        }

        $filepaths = array();
        if (null !== $currentPath && file_exists($currentPath.DIRECTORY_SEPARATOR.$name)) {
            $filepaths[] = $currentPath.DIRECTORY_SEPARATOR.$name;
        }

        foreach ($this->paths as $path) {
            if (file_exists($path.DIRECTORY_SEPARATOR.$name)) {
                $filepaths[] = $path.DIRECTORY_SEPARATOR.$name;
            }
        }

        if (!$filepaths) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist (in: %s%s).', $name, null !== $currentPath ? $currentPath.', ' : '', implode(', ', $this->paths)));
        }

        return true === $first ? $filepaths[0] : $filepaths;
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @param string $file A file path
     *
     * @return Boolean
     */
    protected function isAbsolutePath($file)
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
