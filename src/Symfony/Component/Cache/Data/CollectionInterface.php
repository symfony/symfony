<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface CollectionInterface extends DataInterface
{
    /**
     * @param string $key
     *
     * @return ItemInterface
     *
     * @throws \InvalidArgumentException
     */
    public function get($key);

    /**
     * @return ItemInterface[]
     */
    public function all();

    /**
     * @param ItemInterface $item
     *
     * @return Collection
     */
    public function add(ItemInterface $item);

    /**
     * @param CollectionInterface $collection
     *
     * @return Collection
     */
    public function merge(CollectionInterface $collection);

    /**
     * @return string[]
     */
    public function getKeys();

    /**
     * @return boolean
     */
    public function isEmpty();
}
