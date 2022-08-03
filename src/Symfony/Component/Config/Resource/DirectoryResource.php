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
 *
 * @final
 */
class DirectoryResource implements SelfCheckingResourceInterface
{
    private string $resource;
    private ?string $pattern;

    /**
     * @param string      $resource The file path to the resource
     * @param string|null $pattern  A pattern to restrict monitored files
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $resource, string $pattern = null)
    {
        $resolvedResource = realpath($resource) ?: (file_exists($resource) ? $resource : false);
        $this->pattern = $pattern;

        if (false === $resolvedResource || !is_dir($resolvedResource)) {
            throw new \InvalidArgumentException(sprintf('The directory "%s" does not exist.', $resource));
        }

        $this->resource = $resolvedResource;
    }

    public function __toString(): string
    {
        return md5(serialize([$this->resource, $this->pattern]));
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh(int $timestamp): bool
    {
        if (!is_dir($this->resource)) {
            return false;
        }

        if ($timestamp < filemtime($this->resource)) {
            return false;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->resource), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            // if regex filtering is enabled only check matching files
            if ($this->pattern && $file->isFile() && !preg_match($this->pattern, $file->getBasename())) {
                continue;
            }

            // always monitor directories for changes, except the .. entries
            // (otherwise deleted files wouldn't get detected)
            if ($file->isDir() && str_ends_with($file, '/..')) {
                continue;
            }

            // for broken links
            try {
                $fileMTime = $file->getMTime();
            } catch (\RuntimeException) {
                continue;
            }

            // early return if a file's mtime exceeds the passed timestamp
            if ($timestamp < $fileMTime) {
                return false;
            }
        }

        return true;
    }
}
