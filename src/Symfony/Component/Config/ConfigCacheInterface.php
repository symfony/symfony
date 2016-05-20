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
 * @author Benjamin Klotz <bk@webfactory.de>
 */
interface ConfigCacheInterface
{

    /**
     * Gets the cache file path.
     *
     * @return string The cache file path
     */
    public function __toString();

    /**
     * Checks if the cache is still fresh.
     *
     * @return Boolean true if the cache is fresh, false otherwise
     */
    public function isFresh();

    /**
     * Writes cache.
     *
     * @param string $content  The content to write in the cache
     * @param array  $metadata An array of ResourceInterface instances
     *
     * @throws \RuntimeException When cache file can't be wrote
     */
    public function write($content, array $metadata = null);

}
