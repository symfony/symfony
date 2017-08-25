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
class DirectoryResource implements SelfCheckingResourceInterface, \Serializable
{
    private $resource;
    private $pattern;

    /**
     * Constructor.
     *
     * @param string      $resource The file path to the resource
     * @param string|null $pattern  A pattern to restrict monitored files
     */
    public function __construct($resource, $pattern = null)
    {
        $this->resource = $resource;
        $this->pattern = $pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return md5(serialize(array($this->resource, $this->pattern)));
    }

    /**
     * {@inheritdoc}
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Returns the pattern to restrict monitored files.
     *
     * @return string|null
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($timestamp)
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
            if ($file->isDir() && '/..' === substr($file, -3)) {
                continue;
            }

            // for broken links
            try {
                $fileMTime = $file->getMTime();
            } catch (\RuntimeException $e) {
                continue;
            }

            // early return if a file's mtime exceeds the passed timestamp
            if ($timestamp < $fileMTime) {
                return false;
            }
        }

        return true;
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
