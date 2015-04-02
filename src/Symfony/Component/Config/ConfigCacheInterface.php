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

/**
 * Interface for ConfigCache
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
interface ConfigCacheInterface
{
    /**
     * Gets the cache file path.
     *
     * @deprecated since 2.7, to be removed in 3.0. Use getPath() instead.
     * @return string The cache file path
     */
    public function __toString();

    /**
     * Gets the cache file path.
     *
     * @return string The cache file path
     */
    public function getPath();

    /**
     * Checks if the cache is still fresh.
     *
     * @return bool    true if the cache is fresh, false otherwise
     */
    public function isFresh();

    /**
     * Writes cache.
     *
     * @param string                   $content  The content to write into the cache
     * @param ResourceInterface[]|null $metadata An array of ResourceInterface instances
     *
     * @throws \RuntimeException When the cache file cannot be written
     */
    public function write($content, array $metadata = null);
}
