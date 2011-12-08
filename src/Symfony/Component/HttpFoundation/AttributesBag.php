<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * This class relates to session attribute storage
 */
class AttributesBag implements AttributesBagInterface
{
    /**
     * @var boolean
     */
    private $initialized = false;

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
     * @param type $storageKey The key used to store flashes in the session.
     */
    public function __construct($storageKey = '_sf2_attributes')
    {
        $this->storageKey = $storageKey;
    }

    /**
     * Initializes the AttributesBag
     *
     * @param array &$attributes
     */
    public function initialize(array &$attributes)
    {
        if ($this->initialized) {
            return;
        }

        $this->attributes = &$attributes;
        $this->initialized = true;
    }

    /**
     * Gets the storage key.
     *
     * @return string
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }

    /**
     * Checks if an attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return Boolean true if the attribute is defined, false otherwise
     *
     * @api
     */
    public function has($name)
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Returns an attribute.
     *
     * @param string $name      The attribute name
     * @param mixed  $default   The default value
     *
     * @return mixed
     *
     * @api
     */
    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @api
     */
    public function set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Returns attributes.
     *
     * @return array Attributes
     *
     * @api
     */
    public function all()
    {
        return $this->attributes;
    }

    /**
     * Sets attributes.
     *
     * @param array $attributes Attributes
     *
     * @api
     */
    public function replace(array $attributes)
    {
        $this->attributes = array();
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Removes an attribute.
     *
     * @param string $name
     *
     * @return mixed
     *
     * @api
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
     * Clears all attributes.
     *
     * @api
     */
    public function clear()
    {
        $this->attributes = array();
    }
}
