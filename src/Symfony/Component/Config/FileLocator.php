<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

/**
 * FileLocator uses an array of pre-defined paths to find files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
        $this->paths = (array) $paths;
    }

    /**
     * Returns a full path for a given file name.
     *
     * @param mixed   $name        The file name to locate
     * @param string  $currentPath The current path
     * @param Boolean $first       Whether to return the first occurrence or an array of filenames
     *
     * @return string|array The full path to the file|An array of file paths
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
        if (null !== $currentPath && file_exists($file = $currentPath.DIRECTORY_SEPARATOR.$name)) {
            if (true === $first) {
                return $file;
            }
            $filepaths[] = $file;
        }

        foreach ($this->paths as $path) {
            if (file_exists($file = $path.DIRECTORY_SEPARATOR.$name)) {
                if (true === $first) {
                    return $file;
                }
                $filepaths[] = $file;
            }
        }

        if (!$filepaths) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist (in: %s%s).', $name, null !== $currentPath ? $currentPath.', ' : '', implode(', ', $this->paths)));
        }

        return array_values(array_unique($filepaths));
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @param string $file A file path
     *
     * @return Boolean
     */
    private function isAbsolutePath($file)
    {
        if ($file[0] == '/' || $file[0] == '\\'
            || (strlen($file) > 3 && ctype_alpha($file[0])
                && $file[1] == ':'
                && ($file[2] == '\\' || $file[2] == '/')
            )
            || null !== parse_url($file, PHP_URL_SCHEME)
        ) {
            return true;
        }

        return false;
    }
}
