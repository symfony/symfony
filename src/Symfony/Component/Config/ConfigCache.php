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

use Symfony\Component\Config\Resource\ResourceValidator;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * ConfigCache caches arbitrary content in files on disk.
 *
 * Metadata can be stored alongside the cache and can later be
 * used by MetadataValidators to check if the cache is still fresh.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ConfigCache implements ConfigCacheInterface
{
    private $debug;
    private $file;

    /**
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
     *
     * @deprecated since 2.7, to be removed in 3.0. Use getPath() instead.
     */
    public function __toString()
    {
        @trigger_error('ConfigCache::__toString() is deprecated since version 2.7 and will be removed in 3.0. Use the getPath() method instead.', E_USER_DEPRECATED);

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
     *
     * @deprecated since version 2.8, to be removed in 3.0.
     */
    public function isFresh()
    {
        @trigger_error(__NAMESPACE__.'\ConfigCache::isFresh() is deprecated since version 2.8 and will be removed in 3.0. Use the isValid() method instead and pass the appropriate MetadataValidators, or even better use a \Symfony\Component\Config\ConfigCacheFactoryInterface implementation to create and validate the cache.', E_USER_DEPRECATED);

        if (!$this->debug && is_file($this->file)) {
            return true;
        }

        return $this->isValid(array(new ResourceValidator()));
    }

    /**
     * Use MetadataValidators to check if the cache is still valid.
     *
     * The first MetadataValidator that supports a given resource is considered authoritative.
     * Resources with no matching MetadataValidators will silently be ignored and considered fresh.
     *
     * This method <em>does not</em> take the debug flag into consideration: Whether or not a cache
     * should be checked in production mode and/or which validators need to be applied is a decision
     * left to the client of this method.
     *
     * @param MetadataValidatorInterface[] $validators List of validators the metadata is checked against.
     *                                                 The first validator that supports a resource is considered authoritative.
     *
     * @return bool True if all supported resources and valid, false otherwise
     */
    public function isValid(array $validators = null)
    {
        if (!is_file($this->file)) {
            return false;
        }

        if (!$validators) {
            return true; // shortcut - if we don't have any validators we don't need to bother with the meta file at all
        }

        $metadata = $this->getMetaFile();
        if (!is_file($metadata)) {
            return true;
        }

        $time = filemtime($this->file);
        $meta = unserialize(file_get_contents($metadata));

        foreach ($meta as $resource) {
            foreach ($validators as $validator) {
                if (!$validator->supports($resource)) {
                    continue; // next validator
                }
                if ($validator->isFresh($resource, $time)) {
                    break; // no need to further check this resource
                } else {
                    return false; // cache is stale
                }
            }
            // no suitable validator found, ignore this resource
        }

        return true;
    }

    /**
     * Writes cache.
     *
     * @param string $content  The content to write in the cache
     * @param array  $metadata An array of metadata
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

        if (null !== $metadata) {
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
