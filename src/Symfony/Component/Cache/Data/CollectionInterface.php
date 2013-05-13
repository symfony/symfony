<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Data;

use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Exception\ObjectNotFoundException;

/**
 * Interface for unordered item collections.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface CollectionInterface extends DataInterface
{
    /**
     * Returns an item by its name.
     *
     * @param string $key
     *
     * @return ItemInterface
     *
     * @throws ObjectNotFoundException
     */
    public function get($key);

    /**
     * Returns an array of all items present in the collection.
     *
     * @return ItemInterface[]
     */
    public function all();

    /**
     * Returns an array of all keys present in the collection.
     *
     * @return string[]
     */
    public function getKeys();

    /**
     * Returns an associative array of all key => value present in the collection.
     *
     * @return array
     */
    public function getValues();

    /**
     * Adds an item in the collection.
     *
     * @param ItemInterface $item
     *
     * @throws InvalidArgumentException If given item is not accepted by the collection
     *
     * @return Collection
     */
    public function add(ItemInterface $item);

    /**
     * Merges another collection to this one.
     *
     * @param CollectionInterface $collection
     *
     * @return Collection
     */
    public function merge(CollectionInterface $collection);

    /**
     * Tests if collection is empty (ie. contains no item).
     *
     * @return boolean
     */
    public function isEmpty();

    /**
     * Tests if all items are currently in the cache.
     *
     * @return boolean
     */
    public function isHit();
}
