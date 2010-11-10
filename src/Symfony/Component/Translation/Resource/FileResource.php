<?php

namespace Symfony\Component\Translation\Resource;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FileResource represents a resource stored on the filesystem.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FileResource implements ResourceInterface
{
    protected $resource;

    /**
     * Constructor.
     *
     * @param string $resource The file path to the resource
     */
    public function __construct($resource)
    {
        if (!file_exists($resource)) {
            throw new \InvalidArgumentException(sprintf('Resource "%s" does not exist.', $resource));
        }

        $this->resource = realpath($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return (string) $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function isUptodate($timestamp)
    {
        return filemtime($this->resource) < $timestamp;
    }
}
