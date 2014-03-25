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
use Symfony\Component\Filesystem\Filesystem;

/**
 * ConfigCache is a backwards-compatible way of using the
 * cache implementation classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ConfigCache
{
    private $cacheImplementation;

    /**
     * Constructor.
     *
     * @param string  $file  The absolute cache path
     * @param Boolean $debug Whether debugging is enabled or not
     */
    public function __construct($file, $debug)
    {
        if ($debug) {
            $this->cacheImplementation = new ResourceValidatingCache($file);
        } else {
            $this->cacheImplementation = new NonvalidatingCache($file);
        }
    }

    /**
     * Gets the cache file path.
     *
     * @return string The cache file path
     */
    public function __toString()
    {
        return $this->cacheImplementation->__toString();
    }

    /**
     * Checks if the cache is still fresh.
     *
     * This method always returns true when debug is off and the
     * cache file exists.
     *
     * @return Boolean true if the cache is fresh, false otherwise
     */
    public function isFresh()
    {
        return $this->cacheImplementation->isFresh();
    }

    /**
     * Writes cache.
     *
     * @param string              $content  The content to write in the cache
     * @param ResourceInterface[] $metadata An array of ResourceInterface instances
     *
     * @throws \RuntimeException When the cache file cannot be written.
     */
    public function write($content, array $metadata = null)
    {
        $this->cacheImplementation->write($content, $metadata);
    }
}
