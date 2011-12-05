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
 * FileResource represents a resource stored on the filesystem.
 *
 * The resource can be a file or a directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FileResource implements ResourceInterface
{
    private $resource;

    /**
     * Constructor.
     *
     * @param string $resource The file path to the resource
     */
    public function __construct($resource)
    {
        $this->resource = realpath($resource);
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
     * Returns resource mtime.
     *
     * @return integer
     */
    public function getModificationTime()
    {
        clearstatcache(true, $this->resource);

        return filemtime($this->resource);
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

    /**
     * Returns unique resource ID.
     *
     * @return string
     */
    public function getId()
    {
        return md5($this->resource);
    }

    public function serialize()
    {
        return serialize($this->resource);
    }

    public function unserialize($serialized)
    {
        $this->resource = unserialize($serialized);
    }
}
