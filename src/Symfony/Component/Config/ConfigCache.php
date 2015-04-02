<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * ConfigCache manages PHP cache files.
 *
 * When debug is enabled, it knows when to flush the cache
 * thanks to an array of ResourceInterface instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConfigCache implements ConfigCacheInterface
{
    private $debug;
    private $file;

    /**
     * Constructor.
     *
     * @param string $file  The absolute cache path
     * @param bool   $debug Whether debugging is enabled or not
     */
    public function __construct($file, $debug)
    {
        $this->file = $file;
        $this->debug = (bool) $debug;
    }

    /**
     * Gets the cache file path.
     *
     * @return string The cache file path
     * @deprecated since 2.7, to be removed in 3.0. Use getPath() instead.
     */
    public function __toString()
    {
        return $this->file;
    }

    /**
     * Gets the cache file path.
     *
     * @return string The cache file path
     */
    public function getPath()
    {
        return $this->file;
    }

    /**
     * Checks if the cache is still fresh.
     *
     * This method always returns true when debug is off and the
     * cache file exists.
     *
     * @return bool true if the cache is fresh, false otherwise
     */
    public function isFresh()
    {
        if (!is_file($this->file)) {
            return false;
        }

        if (!$this->debug) {
            return true;
        }

        $metadata = $this->getMetaFile();
        if (!is_file($metadata)) {
            return false;
        }

        $time = filemtime($this->file);
        $meta = unserialize(file_get_contents($metadata));
        foreach ($meta as $resource) {
            if (!$resource->isFresh($time)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes cache.
     *
     * @param string              $content  The content to write in the cache
     * @param ResourceInterface[] $metadata An array of ResourceInterface instances
     *
     * @throws \RuntimeException When cache file can't be written
     */
    public function write($content, array $metadata = null)
    {
        $mode = 0666;
        $umask = umask();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->file, $content, null);
        try {
            $filesystem->chmod($this->file, $mode, $umask);
        } catch (IOException $e) {
            // discard chmod failure (some filesystem may not support it)
        }

        if (null !== $metadata && true === $this->debug) {
            $filesystem->dumpFile($this->getMetaFile(), serialize($metadata), null);
            try {
                $filesystem->chmod($this->getMetaFile(), $mode, $umask);
            } catch (IOException $e) {
                // discard chmod failure (some filesystem may not support it)
            }
        }
    }

    /**
     * Gets the meta file path.
     *
     * @return string The meta file path
     */
    private function getMetaFile()
    {
        return $this->file.'.meta';
    }
}
