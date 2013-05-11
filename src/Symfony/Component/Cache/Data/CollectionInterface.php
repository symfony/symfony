<?php

namespace Symfony\Component\Cache\Data;

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
     * @throws \InvalidArgumentException
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
