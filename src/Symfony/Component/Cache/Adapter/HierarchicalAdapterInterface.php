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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface HierarchicalAdapterInterface extends CacheItemPoolInterface
{
    /**
     * Deletes all items in the pool, optionnaly restricted to a subset of prefix keys.
     *
     * @param $prefix The prefix of keys to delete.
     *
     * @return bool True if the pool was successfully cleared. False if there was an error.
     *
     * @throws InvalidArgumentException If the key prefix contains invalid characters.
     */
    public function clear($prefix = '');

    /**
     * Returns the current separator that is used in cache keys to identify hierarchical levels.
     *
     * @return string
     */
    public function getHierarchicalSeparator();
}
