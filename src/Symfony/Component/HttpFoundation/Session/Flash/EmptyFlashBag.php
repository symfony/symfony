<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Flash;

use Symfony\Component\HttpFoundation\Session\EmptyBag;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\EmptyStorageInterface;

/**
 * A wrapper class for an empty FlashBag flash message container.
 *
 * @author Terje Bråten <terje@braten.be>
 */
class EmptyFlashBag extends EmptyBag implements FlashBagInterface, \IteratorAggregate, \Countable
{
    /**
     * {@inheritdoc}
     */
    public function add($type, $message)
    {
        $this->startSession();
        $this->realBag->add($type, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function peek($type, array $default = array())
    {
        if ($this->isEmpty) {
            return $default;
        }

        return $this->realBag->peek($type, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function peekAll()
    {
        if ($this->isEmpty) {
            return array();
        }

        return $this->realBag->peekAll();
    }

    /**
     * {@inheritdoc}
     */
    public function get($type, array $default = array())
    {
        if ($this->isEmpty) {
            return $default;
        }

        return $this->realBag->get($type, $default);
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
    public function set($type, $messages)
    {
        $this->startSession();
        $this->realBag->set($type, $messages);
    }

    /**
     * {@inheritdoc}
     */
    public function setAll(array $messages)
    {
        $this->startSession();
        $this->realBag->setAll($messages);
    }

    /**
     * {@inheritdoc}
     */
    public function has($type)
    {
        if ($this->isEmpty) {
            return false;
        }

        return $this->realBag->has($type);
    }

    /**
     * {@inheritdoc}
     */
    public function keys()
    {
        if ($this->isEmpty) {
            return array();
        }

        return $this->realBag->keys();
    }

    /**
     * Returns an iterator for flashes.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }

    /**
     * Returns the number of flashes.
     *
     * @return int The number of flashes
     */
    public function count()
    {
        if ($this->isEmpty) {
            return 0;
        }

        return $this->realBag->count();
    }
}
