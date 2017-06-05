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
class FileResource extends FileExistenceResource
{
    /**
     * Constructor.
     *
     * @param string $resource The file path to the resource
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($resource)
    {
        $resource = realpath($resource) ?: $resource;

        parent::__construct($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($timestamp)
    {
        if (!parent::isFresh($timestamp)) {
            return false;
        }

        return !file_exists($this->resource) || filemtime($this->resource) <= $timestamp;
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);

        // compatibility with previously serialized resource
        if (!is_array($unserialized)) {
            $unserialized = array($unserialized, true);
        }

        list($this->resource, $this->exists) = $unserialized;
    }
}
