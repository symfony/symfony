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

    public function __construct(string $dn, array $attributes = array())
    {
        $this->dn = $dn;
        $this->attributes = $attributes;
    }

    /**
     * Returns the entry's DN.
     */
    public function getDn(): string
    {
        return $this->dn;
    }

    /**
     * Returns whether an attribute exists.
     *
     * @param $name string The name of the attribute
     */
    public function hasAttribute($name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Returns a specific attribute's value.
     *
     * As LDAP can return multiple values for a single attribute,
     * this value is returned as an array.
     *
     * @param $name string The name of the attribute
     *
     * @return null|array
     */
    public function getAttribute($name): ?array
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    /**
     * Returns the complete list of attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Sets a value for the given attribute.
     */
    public function setAttribute(string $name, array $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Removes a given attribute.
     */
    public function removeAttribute(string $name): void
    {
        unset($this->attributes[$name]);
    }
}
