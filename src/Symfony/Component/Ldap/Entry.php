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
 * @author Karl Shea <karl@karlshea.com>
 */
class Entry
{
    private $dn;

    /**
     * @var array<string, array>
     */
    private $attributes = [];

    /**
     * @var array<string, string>
     */
    private $lowerMap = [];

    /**
     * @param array<string, array> $attributes
     */
    public function __construct(string $dn, array $attributes = [])
    {
        $this->dn = $dn;

        foreach ($attributes as $key => $attribute) {
            $this->setAttribute($key, $attribute);
        }
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
     * @param string $name          The name of the attribute
     * @param bool   $caseSensitive Whether the check should be case-sensitive
     *
     * @return bool
     */
    public function hasAttribute(string $name/* , bool $caseSensitive = true */)
    {
        $caseSensitive = 2 > \func_num_args() || true === func_get_arg(1);
        $attributeKey = $this->getAttributeKey($name, $caseSensitive);

        if (null === $attributeKey) {
            return false;
        }

        return isset($this->attributes[$attributeKey]);
    }

    /**
     * Returns a specific attribute's value.
     *
     * As LDAP can return multiple values for a single attribute,
     * this value is returned as an array.
     *
     * @param string $name          The name of the attribute
     * @param bool   $caseSensitive Whether the attribute name is case-sensitive
     *
     * @return array|null
     */
    public function getAttribute(string $name/* , bool $caseSensitive = true */)
    {
        $caseSensitive = 2 > \func_num_args() || true === func_get_arg(1);
        $attributeKey = $this->getAttributeKey($name, $caseSensitive);

        if (null === $attributeKey) {
            return null;
        }

        return $this->attributes[$attributeKey] ?? null;
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
     */
    public function setAttribute(string $name, array $value)
    {
        $this->attributes[$name] = $value;
        $this->lowerMap[strtolower($name)] = $name;
    }

    /**
     * Removes a given attribute.
     */
    public function removeAttribute(string $name)
    {
        unset($this->attributes[$name]);
        unset($this->lowerMap[strtolower($name)]);
    }

    private function getAttributeKey(string $name, bool $caseSensitive = true): ?string
    {
        if ($caseSensitive) {
            return $name;
        }

        return $this->lowerMap[strtolower($name)] ?? null;
    }
}
