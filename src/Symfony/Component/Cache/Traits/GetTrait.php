<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait GetTrait
{
    /**
     * {@inheritdoc}
     */
    public function get(string $key, callable $callback)
    {
        return $this->doGet($this, $key, $callback);
    }

    private function doGet(CacheItemPoolInterface $pool, string $key, callable $callback)
    {
        $item = $pool->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $pool->save($item->set($value = $callback($item)));

        return $value;
    }
}
