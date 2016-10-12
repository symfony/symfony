<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

class Alias
{
    private $id;
    private $public;
    private $autowiringTypes = array();

    /**
     * @param string $id     Alias identifier
     * @param bool   $public If this alias is public
     */
    public function __construct($id, $public = true, $autowiringTypes = array())
    {
        $this->id = strtolower($id);
        $this->public = $public;
        $this->setAutowiringTypes($autowiringTypes);
    }

    /**
     * Checks if this DI Alias should be public or not.
     *
     * @return bool
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * Sets if this Alias is public.
     *
     * @param bool $boolean If this Alias should be public
     */
    public function setPublic($boolean)
    {
        $this->public = (bool) $boolean;
    }

    /**
     * Gets autowiring types that will default to this alias.
     *
     * @return string[]
     */
    public function getAutowiringTypes()
    {
        return array_keys($this->autowiringTypes);
    }

    /**
     * Will this alias default for the given type?
     *
     * @param string $type
     *
     * @return bool
     */
    public function hasAutowiringType($type)
    {
        return isset($this->autowiringTypes[$type]);
    }

    /**
     * Adds a type that will default to this alias.
     *
     * @param string $type
     *
     * @return Alias The current instance
     */
    public function addAutowiringType($type)
    {
        $this->autowiringTypes[$type] = true;

        return $this;
    }

    /**
     * Removes a type.
     *
     * @param string $type
     *
     * @return Alias The current instance
     */
    public function removeAutowiringType($type)
    {
        unset($this->autowiringTypes[$type]);

        return $this;
    }

    /**
     * Sets types that will default to this alias.
     *
     * @param string[] $types
     *
     * @return Alias The current instance
     */
    public function setAutowiringTypes(array $types)
    {
        $this->autowiringTypes = array();

        foreach ($types as $type) {
            $this->autowiringTypes[$type] = true;
        }

        return $this;
    }

    /**
     * Returns the Id of this alias.
     *
     * @return string The alias id
     */
    public function __toString()
    {
        return $this->id;
    }
}
