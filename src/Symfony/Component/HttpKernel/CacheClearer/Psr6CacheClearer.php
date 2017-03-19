<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheClearer;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Psr6CacheClearer implements CacheClearerInterface
{
    private $pools = array();

    public function addPool(CacheItemPoolInterface $pool)
    {
        $this->pools[] = $pool;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($cacheDir)
    {
        foreach ($this->pools as $pool) {
            $pool->clear();
        }
    }
}
