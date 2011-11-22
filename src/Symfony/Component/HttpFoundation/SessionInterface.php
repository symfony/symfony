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
 * Interface for the session.
 */
interface SessionInterface extends \Serializable
{
    /**
     * Starts the session storage.
     *
     * @api
     */
    function start();

    /**
     * Checks if an attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return Boolean true if the attribute is defined, false otherwise
     *
     * @api
     */
    function has($name, $namespace = '/');

    /**
     * Returns an attribute.
     *
     * @param string $name      The attribute name
     * @param mixed  $default   The default value
     * @param string $namespace Namespace
     *
     * @return mixed
     *
     * @api
     */
    function get($name, $default = null, $namespace = '/');

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     * @param string $namespace
     *
     * @api
     */
    function set($name, $value, $namespace = '/');

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
     * @param string $namespace
     *
     * @api
     */
    function remove($name, $namespace = '/');

    /**
     * Clears all attributes.
     *
     * @api
     */
    function clear();

    /**
     * Invalidates the current session.
     *
     * @api
     */
    function invalidate();
}
