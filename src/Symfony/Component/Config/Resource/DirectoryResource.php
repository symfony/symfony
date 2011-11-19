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
class DirectoryResource implements ResourceInterface, \Serializable
{
    private $resource;
    private $pattern;

    /**
     * Constructor.
     *
     * @param string $resource The file path to the resource
     * @param string $pattern  A pattern to restrict monitored files
     */
    public function __construct($resource, $pattern = null)
    {
        $this->resource = $resource;
        $this->pattern = $pattern;
    }

    /**
     * Returns the list of filtered file and directory childs of directory resource.
     *
     * @param  Boolean $recursive search for files recursive
     *
     * @return array An array of files
     */
    public function getFilteredChilds($recursive = true)
    {
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->resource), \RecursiveIteratorIterator::SELF_FIRST)
            : new \DirectoryIterator($this->resource);

        $childs = array();
        foreach ($iterator as $file) {
            // if regex filtering is enabled only return matching files
            if (isset($this->filterRegexList) && $file->isFile()) {
                $regexMatched = false;
                foreach ($this->filterRegexList as $regex) {
                    if (preg_match($regex, (string) $file)) {
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

            $childs[] = $file;
        }

        return $childs;
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

    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Returns resource mtime.
     *
     * @return integer
     */
    public function getModificationTime()
    {
        clearstatcache(true, $this->resource);
        $newestMTime = filemtime($this->resource);

        foreach ($this->getFilteredChilds() as $file) {
            clearstatcache(true, (string) $file);
            $newestMTime = max($file->getMTime(), $newestMTime);
        }

        return $newestMTime;
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
        if (!$this->exists()) {
            return false;
        }

        return $this->getModificationTime() <= $timestamp;
    }

    /**
     * Returns true if the resource exists in the filesystem.
     *
     * @return Boolean
     */
    public function exists()
    {
        return file_exists($this->resource);
    }

    public function serialize()
    {
        return serialize(array($this->resource, $this->pattern));
    }

    public function unserialize($serialized)
    {
        list($this->resource, $this->pattern) = unserialize($serialized);
    }
}
