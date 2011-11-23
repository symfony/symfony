<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

/**
 * Interface for the session.
 * 
 * @api
 */
interface AttributeInterface
{
    const STORAGE_KEY = '_sf2_attributes';
    
    /**
     * Checks if an attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return Boolean true if the attribute is defined, false otherwise
     *
     * @api
     */
    function has($name);

    /**
     * Returns an attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The default value
     *
     * @return mixed
     *
     * @api
     */
    function get($name, $default = null);

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @api
     */
    function set($name, $value);

    /**
     * Returns attributes.
     *
     * @return array Attributes
     *
     * @api
     */
    function all();

    /**
     * Sets attributes.
     *
     * @param array $attributes Attributes
     *
     * @api
     */
    function replace(array $attributes);

    /**
     * Removes an attribute.
     *
     * @param string $name
     *
     * @api
     */
    function remove($name);

    /**
     * Clears all attributes.
     *
     * @api
     */
    function clear();
}
