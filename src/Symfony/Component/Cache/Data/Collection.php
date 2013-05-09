<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Collection implements CollectionInterface
{
    /**
     * @var ItemInterface[]
     */
    private $items = array();

    /**
     * @var boolean
     */
    private $valid = true;

    /**
     * @var bool
     */
    private $cached = true;

    /**
     * @param ItemInterface[] $items
     */
    public function __construct(array $items = array())
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        if (!isset($this->items[$key])) {
            throw new \InvalidArgumentException('Item not found.');
        }

        return $this->items[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * {@inheritdoc}
     */
    public function getKeys()
    {
        return array_keys($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function add(ItemInterface $item)
    {
        $this->items[$item->getKey()] = $item;
        $this->valid = $this->valid && $item->isValid();
        $this->cached = $this->cached && $item->isCached();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(CollectionInterface $collection)
    {
        foreach ($collection->all() as $item) {
            $this->add($item);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * {@inheritdoc}
     */
    public function isCached()
    {
        return $this->cached;
    }

    /**
     * {@inheritdoc}
     */
    public function isCollection()
    {
        return true;
    }
}
