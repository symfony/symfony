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
 * This class provides structured storage of session attributes using the
 */
class AttributesNamespacedBag extends AttributesBag implements AttributesBagInterface
{
    /**
     * Namespace character.
     *
     * @var string
     */
    private $namespaceCharacter;

    /**
     * Constructor.
     *
     * @param type $storageKey         Session storage key.
     * @param type $namespaceCharacter Namespace character to use in keys.
     */
    public function __construct($storageKey = '_sf2_attributes', $namespaceCharacter = '/')
    {
        $this->namespaceCharacter = $namespaceCharacter;
        parent::__construct($storageKey);
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
        $attributes = $this->resolveAttributePath($name);
        $name = $this->resolveKey($name);

        return array_key_exists($name, $attributes);
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
        $attributes = $this->resolveAttributePath($name);
        $name = $this->resolveKey($name);

        return array_key_exists($name, $attributes) ? $attributes[$name] : $default;
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
        $attributes = & $this->resolveAttributePath($name, true);
        $name = $this->resolveKey($name);
        $attributes[$name] = $value;
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
        $attributes = & $this->resolveAttributePath($name);
        $name = $this->resolveKey($name);
        if (array_key_exists($name, $attributes)) {
            $retval = $attributes[$name];
            unset($attributes[$name]);
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

    /**
     * Resolves a path in attributes property and returns it as a reference.
     *
     * This method allows structured namespacing of session attributes.
     *
     * @param string  $name         Key name
     * @param boolean $writeContext Write context, default false
     *
     * @return array
     */
    protected function &resolveAttributePath($name, $writeContext = false)
    {
        $array = & $this->attributes;
        $name = (strpos($name, $this->namespaceCharacter) === 0) ? substr($name, 1) : $name;

        // Check if there is anything to do, else return
        if (!$name) {
            return $array;
        }

        $parts = explode($this->namespaceCharacter, $name);
        if (count($parts) < 2) {
            if (!$writeContext) {
                return $array;
            }
            $array[$parts[0]] = array();
            return $array;
        }
        unset($parts[count($parts)-1]);

        foreach ($parts as $part) {
            if (!array_key_exists($part, $array)) {
                if (!$writeContext) {
                    return $array;
                }
                $array[$part] = array();
            }

            $array = & $array[$part];
        }

        return $array;
    }

    /**
     * Resolves the key from the name.
     *
     * This is the last part in a dot separated string.
     *
     * @param string $name
     *
     * @return string
     */
    protected function resolveKey($name)
    {
        if (strpos($name, $this->namespaceCharacter) !== false) {
            $name = substr($name, strrpos($name, $this->namespaceCharacter)+1, strlen($name));
        }

        return $name;
    }
}
