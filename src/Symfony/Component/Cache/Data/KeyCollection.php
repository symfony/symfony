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
        throw new \LogicException('Can not add item to key collection.');
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
    public function get($key)
    {
        throw new \LogicException('Key collection has no item.');
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        throw new \LogicException('Key collection has no item.');
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCached()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCollection()
    {
        return true;
    }
}
