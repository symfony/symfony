<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * A simple implementation of CacheInterface::get() for PSR-6 CacheItemPoolInterface classes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait GetForCacheItemPoolTrait
{
    /**
     * {@inheritdoc}
     */
    public function get(string $key, callable $callback, float $beta = null)
    {
        if (0 > $beta) {
            throw new class(sprintf('Argument "$beta" provided to "%s::get()" must be a positive number, %f given.', \get_class($this), $beta)) extends \InvalidArgumentException implements InvalidArgumentException {
            };
        }

        $item = $this->getItem($key);

        if (INF === $beta || !$item->isHit()) {
            $value = $callback($item);
            $item->set($value);
            $this->save($item);
        }

        return $item->get();
    }
}
