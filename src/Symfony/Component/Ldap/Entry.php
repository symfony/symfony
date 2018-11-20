<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
class Entry
{
    private $dn;
    private $attributes;

    public function __construct($dn, array $attributes = array())
    {
        $this->dn = $dn;
        $this->attributes = $attributes;
    }

    /**
     * Returns the entry's DN.
     *
     * @return string
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * Returns whether an attribute exists.
     *
     * @param string $name The name of the attribute
     *
     * @return bool
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Returns a specific attribute's value.
     *
     * As LDAP can return multiple values for a single attribute,
     * this value is returned as an array.
     *
     * @param string $name The name of the attribute
     *
     * @return array|null
     */
    public function getAttribute($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    /**
     * Returns the complete list of attributes.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Sets a value for the given attribute.
     *
     * @param string $name
     * @param array  $value
     */
    public function setAttribute($name, array $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Removes a given attribute.
     *
     * @param string $name
     */
    public function removeAttribute($name)
    {
        unset($this->attributes[$name]);
    }
}
