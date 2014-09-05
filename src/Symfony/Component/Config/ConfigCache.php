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
use Symfony\Component\Config\Resource\MutableResourceInterface;
use Symfony\Component\Config\Resource\Refresher\RefresherInterface;
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
class ConfigCache
{
    private $debug;
    private $file;

    /**
     * Constructor.
     *
     * @param string  $file  The absolute cache path
     * @param bool    $debug Whether debugging is enabled or not
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
     */
    public function __toString()
    {
        return $this->file;
    }

    /**
     * Checks if the cache is still fresh.
     *
     * This method always returns true when debug is off and the
     * cache file exists.
     *
     * @return bool    true if the cache is fresh, false otherwise
     */
    public function isFresh(RefresherInterface $refresher = null)
    {
        if (!is_file($this->file)) {
            return false;
        }

        $metadata = $this->getMetaFile();
        if (!is_file($metadata)) {
            return true;
        }

        $meta = unserialize(file_get_contents($metadata));
        $timestamp = filemtime($this->file);
        foreach ($meta as $resource) {
            if (null !== $refresher && $resource instanceof MutableResourceInterface) {
                $refresher->refresh($resource);
            }

            if (!$resource->isFresh($timestamp)) {
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
     * @throws \RuntimeException When cache file can't be wrote
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

        $mutables = array();
        if (is_array($metadata)) {
            foreach ($metadata as $resource) {
                if ($resource instanceof MutableResourceInterface) {
                    if (true === $resource->isMutable()) {
                        $mutables[] = $resource;
                    }
                } else {
                    if (true === $this->debug) {
                        $mutables[] = $resource;
                    }
                }
            }
        }

        if (count($mutables) > 0) {
            $filesystem->dumpFile($this->getMetaFile(), serialize($mutables), null);
            try {
                $filesystem->chmod($this->getMetaFile(), $mode, $umask);
            } catch (IOException $e) {
                // discard chmod failure (some filesystem may not support it)
            }
        } else {
            $filesystem->remove($this->getMetaFile());
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
