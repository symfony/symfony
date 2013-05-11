<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class KeyCollection implements CollectionInterface
{
    /**
     * @var string[]
     */
    private $keys;

    /**
     * @param string[] $keys
     */
    public function __construct(array $keys = array())
    {
        $this->keys = $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function add(ItemInterface $item)
    {
        throw new \BadMethodCallException('Can not add item to key collection.');
    }

    /**
     * {@inheritdoc}
     */
    public function merge(CollectionInterface $collection)
    {
        $this->keys = array_merge($this->keys, $collection->getKeys());

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getKeys()
    {
        return $this->keys;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        throw new \BadMethodCallException('Key collection has no value.');
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        throw new \BadMethodCallException('Key collection has no value.');
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        throw new \BadMethodCallException('Key collection has no value.');
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return empty($this->keys);
    }


    /**
     * {@inheritdoc}
     */
    public function isHit()
    {
        return false;
    }
}
