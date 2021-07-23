<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Exception\CacheException;

class DebugAdapter extends ProxyAdapter
{
    /**
     * @throws CacheException in case cache item could not get saved
     */
    public function save(CacheItemInterface $item)
    {
        $result = parent::save($item);

        if (!$result) {
            throw new CacheException("Can not save cache item {$item->get()}");
        }

        return $result;
    }

    /**
     * @throws CacheException in case cache item could not get saved
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $result = parent::saveDeferred($item);

        if (!$result) {
            throw new CacheException("Can not save cache item {$item->get()}");
        }

        return $result;
    }
}
