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
    private ?string $hash = null;

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

        // mtimes have a one-second resolution: remember the content of recently modified files to tell if they change again in the same second
        if (time() - 1 <= @filemtime($resolvedResource)) {
            $this->hash = @hash_file('xxh128', $resolvedResource) ?: null;
        }
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
        if (false === $filemtime = @filemtime($this->resource)) {
            return false;
        }

        if ($filemtime !== $timestamp) {
            return $filemtime < $timestamp;
        }

        // mtimes have a one-second resolution: a file modified in the same second as $timestamp may have changed after being loaded
        return null !== $this->hash && $this->hash === @hash_file('xxh128', $this->resource);
    }

    public function __serialize(): array
    {
        $data = ['resource' => $this->resource];

        if (null !== $this->hash) {
            $data['hash'] = $this->hash;
        }

        return $data;
    }
}
