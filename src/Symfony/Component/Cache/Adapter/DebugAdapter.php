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
    public function save(CacheItemInterface $item)
    {
        $result = parent::save($item);

        if (!$result) {
            throw new CacheException("Can not save cache item {$item->get()}");
        }

        return $result;
    }
}
