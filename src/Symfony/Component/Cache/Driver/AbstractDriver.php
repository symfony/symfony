<?php

namespace Symfony\Component\Cache\Driver;

use Symfony\Component\Cache\Data\ValidItem;
use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\CollectionInterface;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\ItemInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Data\NullResult;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * {@inheritdoc}
     */
    public function fetch(DataInterface $data)
    {
        if ($data instanceof CollectionInterface) {
            $result = $this->fetchMany($data->getKeys());
        } elseif ($data instanceof ItemInterface) {
            $result = $this->fetchOne($data->getKey());
        } else {
            throw new \InvalidArgumentException('Invalid data.');
        }

        if (0 === count($result)) {
            return new NullResult();
        }

        if (1 === count($result)) {
            $keys = array_keys($result);

            return new CachedItem(reset($keys), reset($result));
        }

        return new Collection(array_map(function ($key, $data) {
            return new CachedItem($key, $data);
        }, array_keys($result), $result));
    }

    /**
     * {@inheritdoc}
     */
    public function store(DataInterface $data)
    {
        if ($data instanceof CollectionInterface) {
            $raw = array();
            foreach ($data->all() as $item) {
                $raw[$item->getKey()] = $item->getData();
            }

            if ($this->storeMany($raw)) {
                return new Collection(array_map(function (ItemInterface $item) {
                    return CachedItem::duplicate($item);
                }, $data->all()));
            }

            return $data;
        }

        if ($data instanceof ValidItem) {
            if ($this->storeOne($data->getKey(), $data->getData())) {
                return CachedItem::duplicate($data);
            }

            return $data;
        }

        throw new \InvalidArgumentException('Invalid data.');
    }

    /**
     * {@inheritdoc}
     */
    public function delete(KeyCollection $data)
    {
        $keys = $data->getKeys();

        if (count($keys) > 1) {
            return new KeyCollection($this->deleteMany($keys));
        }

        if (count($keys) === 1) {
            return new KeyCollection($this->deleteOne(reset($keys)));
        }

        return new KeyCollection();
    }

    /**
     * @param string $key
     *
     * @return array
     */
    abstract protected function fetchOne($key);

    /**
     * @param array $keys
     *
     * @return array
     */
    abstract protected function fetchMany(array $keys);

    /**
     * @param string $key
     * @param mixed  $data
     *
     * @return boolean
     */
    abstract protected function storeOne($key, $data);

    /**
     * @param string[] $data
     *
     * @return boolean
     */
    abstract protected function storeMany(array $data);

    /**
     * @param string $key
     *
     * @return array
     */
    abstract protected function deleteOne($key);

    /**
     * @param string[] $keys
     *
     * @return array
     */
    abstract protected function deleteMany(array $keys);
}
