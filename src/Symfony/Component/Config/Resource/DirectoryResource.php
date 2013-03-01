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
class DirectoryResource implements SelfValidatingResourceInterface
{
    private $directory;
    private $pattern;
    private $mtime;

    /**
     * Constructor.
     *
     * @param string $directory The file path to the resource
     * @param string $pattern  A pattern to restrict monitored files
     */
    public function __construct($directory, $pattern = null)
    {
        if (is_dir($directory)) {
            $this->directory = $directory;
            $this->mtime = $this->scanNewestMtime();
        }
        $this->pattern = $pattern;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Returns true if the resource has not been updated since the given timestamp.
     *
     * @param integer $timestamp The last time the resource was loaded
     *
     * @return Boolean true if the resource has not been updated, false otherwise
     */
    public function isFresh()
    {
        if (!$this->directory || !is_dir($this->directory)) {
            return false;
        }

        return $this->scanNewestMtime() == $this->mtime;
    }

    public function scanNewestMtime()
    {
        $newestMTime = filemtime($this->directory);

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            // if regex filtering is enabled only check matching files
            if ($this->pattern && $file->isFile() && !preg_match($this->pattern, $file->getBasename())) {
                continue;
            }

            // always monitor directories for changes, except the .. entries
            // (otherwise deleted files wouldn't get detected)
            if ($file->isDir() && '/..' === substr($file, -3)) {
                continue;
            }

            $newestMTime = max($file->getMTime(), $newestMTime);
        }

        return $newestMTime;
    }

    public function serialize()
    {
        return serialize(array(
            'directory' => $this->filename,
            'pattern' => $this->pattern,
            'mtime' => $this->mtime
        ));
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->directory = $data['directory'];
        $this->pattern = $data['pattern'];
        $this->mtime = $data['mtime'];
    }

}
