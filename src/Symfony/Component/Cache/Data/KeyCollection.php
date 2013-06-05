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

use Symfony\Component\Cache\Exception\BadMethodCallException;

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
        $this->keys[] = $item->getKey();

        return $this;
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
        throw new BadMethodCallException('Key collection does not contain values.');
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        throw new BadMethodCallException('Key collection does not contain values.');
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        throw new BadMethodCallException('Key collection does not contain values.');
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
