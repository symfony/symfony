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
 * A ConfigCacheFactory implementation that validates the
 * cache with an arbitrary set of ResourceCheckers.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ResourceCheckerConfigCacheFactory implements ConfigCacheFactoryInterface
{
    private iterable $resourceCheckers = [];

    /**
     * @param iterable<int, ResourceCheckerInterface> $resourceCheckers
     */
    public function __construct(iterable $resourceCheckers = [])
    {
        $this->resourceCheckers = $resourceCheckers;
    }

    public function cache(string $file, callable $callable): ConfigCacheInterface
    {
        $cache = new ResourceCheckerConfigCache($file, $this->resourceCheckers);
        if (!$cache->isFresh()) {
            $callable($cache);
        }

        return $cache;
    }
}
