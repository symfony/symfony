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
 *
 * @since v2.1.0
 */
class FileResource implements ResourceInterface, \Serializable
{
    private $resource;

    /**
     * Constructor.
     *
     * @param string $resource The file path to the resource
     *
     * @since v2.0.0
     */
    public function __construct($resource)
    {
        $this->resource = realpath($resource);
    }

    /**
     * Returns a string representation of the Resource.
     *
     * @return string A string representation of the Resource
     *
     * @since v2.0.0
     */
    public function __toString()
    {
        return (string) $this->resource;
    }

    /**
     * Returns the resource tied to this Resource.
     *
     * @return mixed The resource
     *
     * @since v2.0.0
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
     *
     * @since v2.0.0
     */
    public function isFresh($timestamp)
    {
        if (!file_exists($this->resource)) {
            return false;
        }

        return filemtime($this->resource) < $timestamp;
    }

    /**
     * @since v2.1.0
     */
    public function serialize()
    {
        return serialize($this->resource);
    }

    /**
     * @since v2.1.0
     */
    public function unserialize($serialized)
    {
        $this->resource = unserialize($serialized);
    }
}
