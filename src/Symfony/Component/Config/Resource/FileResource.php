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
 * @final
 */
class FileResource implements SelfCheckingResourceInterface
{
    private string $resource;

    /**
     * @param string $resource The file path to the resource
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $resource)
    {
        $resolvedResource = realpath($resource) ?: (file_exists($resource) ? $resource : false);

        if (false === $resolvedResource) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" does not exist.', $resource));
        }

        $this->resource = $resolvedResource;
    }

    public function __toString(): string
    {
        return $this->resource;
    }

    /**
     * Returns the canonicalized, absolute path to the resource.
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    public function isFresh(int $timestamp): bool
    {
        // A resource modified in the same second as the timestamp was taken
        // counts as stale: filemtime() has a one-second resolution, so the
        // resource may have changed after it was read but before the cache
        // file was written (see #67939).
        return false !== ($filemtime = @filemtime($this->resource)) && $filemtime < $timestamp;
    }
}
