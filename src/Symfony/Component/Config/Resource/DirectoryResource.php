<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * DirectoryResource represents a resources stored in a subdirectory tree.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DirectoryResource implements ResourceInterface
{
    private $resource;
    private $filterRegexList;

    /**
     * Constructor.
     *
     * @param string $resource The file path to the resource
     */
    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    /**
     * Set a list of filter regex (to restrict the list of monitored files)
     *
     * @param array $filterRegexList An array of regular expressions
     */
    public function setFilterRegexList(array $filterRegexList)
    {
        $this->filterRegexList = $filterRegexList;
    }

    /**
     * Returns the list of filter regex
     *
     * @return array An array of regular expressions
     */
    public function getFilterRegexList()
    {
        return $this->filterRegexList;
    }

    /**
     * Returns a string representation of the Resource.
     *
     * @return string A string representation of the Resource
     */
    public function __toString()
    {
        return (string) $this->resource;
    }

    /**
     * Returns the resource tied to this Resource.
     *
     * @return mixed The resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Returns true if the resource has not been updated since the given timestamp.
     *
     * @param integer $timestamp The last time the resource was loaded
     *
     * @return Boolean true if the resource has not been updated, false otherwise
     */
    public function isFresh($timestamp)
    {
        if (!file_exists($this->resource)) {
            return false;
        }

        $newestMTime = filemtime($this->resource);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->resource), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            // if regex filtering is enabled only check matching files
            if (isset($this->filterRegexList) && $file->isFile()) {
                $regexMatched = false;
                foreach ($this->filterRegexList as $regex) {
                    if (preg_match($regex, $file->__toString())) {
                        $regexMatched = true;
                    }
                }
                if (!$regexMatched) {
                  continue;
                }
            }
            // always monitor directories for changes, except the .. entries
            // (otherwise deleted files wouldn't get detected)
            if ($file->isDir() && '/..' === substr($file, -3)) {
                continue;
            }
            $newestMTime = max($file->getMTime(), $newestMTime);
        }

        return $newestMTime < $timestamp;
    }
}
