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
 * GlobLocator uses an array of pre-defined paths to find files by glob pattern.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class GlobLocator implements FileLocatorInterface
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
     * Gets all absolute paths matched by expanding the glob pattern within all
     * resource search paths.
     *
     * @param string  $glob
     * @param string  $currentPath The current path
     * @param Boolean $first       Whether to return the first occurrence or an array of filenames
     *
     * @return array An array of paths matching the glob pattern
     *
     * @throws \InvalidArgumentException When file is not found
     */
    public function locate($glob, $currentPath = null, $first = true)
    {
        $dirs = array();
        foreach ($this->paths as $path) {
            if (false !== ($d = glob($path.DIRECTORY_SEPARATOR.$glob, GLOB_ONLYDIR | GLOB_BRACE))) {
                $dirs = array_merge($dirs, $d);
            }
        }

        return $dirs;
    }
}
