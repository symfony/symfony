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

/**
 * FlashBag flash message container.
 *
 * \IteratorAggregate implementation is deprecated and will be removed in 3.0.
 *
 * @author Drak <drak@zikula.org>
 *
 * @since v2.3.0
 */
class FlashBag implements FlashBagInterface, \IteratorAggregate
{
    private $name = 'flashes';

    /**
     * Flash messages.
     *
     * @var array
     */
    private $flashes = array();

    /**
     * The storage key for flashes in the session
     *
     * @var string
     */
    private $storageKey;

    /**
     * Constructor.
     *
     * @param string $storageKey The key used to store flashes in the session.
     *
     * @since v2.1.0
     */
    public function __construct($storageKey = '_sf2_flashes')
    {
        $this->storageKey = $storageKey;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @since v2.1.0
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function initialize(array &$flashes)
    {
        $this->flashes = &$flashes;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function add($type, $message)
    {
        $this->flashes[$type][] = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function peek($type, array $default = array())
    {
        return $this->has($type) ? $this->flashes[$type] : $default;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function peekAll()
    {
        return $this->flashes;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function get($type, array $default = array())
    {
        if (!$this->has($type)) {
            return $default;
        }

        $return = $this->flashes[$type];

        unset($this->flashes[$type]);

        return $return;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function all()
    {
        $return = $this->peekAll();
        $this->flashes = array();

        return $return;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function set($type, $messages)
    {
        $this->flashes[$type] = (array) $messages;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function setAll(array $messages)
    {
        $this->flashes = $messages;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function has($type)
    {
        return array_key_exists($type, $this->flashes) && $this->flashes[$type];
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function keys()
    {
        return array_keys($this->flashes);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function clear()
    {
        return $this->all();
    }

    /**
     * Returns an iterator for flashes.
     *
     * @deprecated Will be removed in 3.0.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     *
     * @since v2.1.0
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->all());
    }
}
