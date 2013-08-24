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

/**
 * This class relates to session attribute storage
 *
 * @since v2.1.0
 */
class AttributeBag implements AttributeBagInterface, \IteratorAggregate, \Countable
{
    private $name = 'attributes';

    /**
     * @var string
     */
    private $storageKey;

    /**
     * @var array
     */
    protected $attributes = array();

    /**
     * Constructor.
     *
     * @param string $storageKey The key used to store attributes in the session.
     *
     * @since v2.1.0
     */
    public function __construct($storageKey = '_sf2_attributes')
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
    public function initialize(array &$attributes)
    {
        $this->attributes = &$attributes;
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
    public function has($name)
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function all()
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function replace(array $attributes)
    {
        $this->attributes = array();
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function remove($name)
    {
        $retval = null;
        if (array_key_exists($name, $this->attributes)) {
            $retval = $this->attributes[$name];
            unset($this->attributes[$name]);
        }

        return $retval;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function clear()
    {
        $return = $this->attributes;
        $this->attributes = array();

        return $return;
    }

    /**
     * Returns an iterator for attributes.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     *
     * @since v2.1.0
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->attributes);
    }

    /**
     * Returns the number of attributes.
     *
     * @return int The number of attributes
     *
     * @since v2.1.0
     */
    public function count()
    {
        return count($this->attributes);
    }
}
