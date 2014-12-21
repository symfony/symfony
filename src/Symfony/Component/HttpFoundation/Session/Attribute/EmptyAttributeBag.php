<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Attribute;

use Symfony\Component\HttpFoundation\Session\EmptyBag;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\EmptyStorageInterface;

/**
 * A wrapper class for an empty attribute bag
 *
 * @author Terje Bråten <terje@braten.be>
 */
class EmptyAttributeBag extends EmptyBag implements AttributeBagInterface, \IteratorAggregate, \Countable
{
    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        if ($this->isEmpty) {
            return false;
        }

        return $this->realBag->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name, $default = null)
    {
        if ($this->isEmpty) {
            return $default;
        }

        return $this->realBag->get($name, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        $this->startSession();
        $this->realBag->set($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        if ($this->isEmpty) {
            return array();
        }

        return $this->realBag->all();
    }

    /**
     * {@inheritdoc}
     */
    public function replace(array $attributes)
    {
        $this->startSession();
        $this->realBag->replace($attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        if ($this->isEmpty) {
            return null;
        }

        return $this->realBag->remove($name);
    }

    /**
     * Returns an iterator for attributes.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }

    /**
     * Returns the number of attributes.
     *
     * @return int The number of attributes
     */
    public function count()
    {
        if ($this->isEmpty) {
            return 0;
        }

        return $this->realBag->count();
    }
}
