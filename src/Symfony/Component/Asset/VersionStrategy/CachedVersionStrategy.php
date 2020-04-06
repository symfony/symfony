<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\VersionStrategy;

use Symfony\Contracts\Cache\CacheInterface;

/**
 * Cache generated versions and path of any version strategy.
 *
 * @author Jérôme TAMARELLE <jerome@tamarelle.net>
 */
class CachedVersionStrategy implements VersionStrategyInterface
{
    private $strategy;

    private $cache;

    public function __construct(VersionStrategyInterface $strategy, CacheInterface $cache)
    {
        $this->strategy = $strategy;
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(string $path)
    {
        return $this->cache->get('v_'.rawurlencode($path), function () use ($path) {
            return $this->strategy->getVersion($path);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function applyVersion(string $path)
    {
        return $this->cache->get('p_'.rawurlencode($path), function () use ($path) {
            return $this->strategy->applyVersion($path);
        });
    }
}
