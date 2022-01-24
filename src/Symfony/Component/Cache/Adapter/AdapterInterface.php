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
use Psr\Cache\CacheItemInterface;

// Help opcache.preload discover always-needed symbols
class_exists(CacheItemInterface::class);

/**
 * Interface for adapters managing instances of Symfony's CacheItem.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface AdapterInterface extends CacheItemPoolInterface
{
    /**
     * {@inheritdoc}
     */
    public function getItem(mixed $key): CacheItemInterface;

    /**
     * {@inheritdoc}
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable;

    /**
     * {@inheritdoc}
     */
    public function clear(string $prefix = ''): bool;
}
