<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session;

/**
 * Interface for the session.
 *
 * @author Drak <drak@zikula.org>
 */
interface SessionInterface extends \Serializable
{
    /**
     * Starts the session storage.
     *
     * @throws \RuntimeException If session fails to start.
     */
    function start();

    /**
     * Invalidates the current session.
     *
     * @return boolean True if session invalidated, false if error.
     */
    function invalidate();

    /**
     * Migrates the current session to a new session id while maintaining all
     * session attributes.
     *
     * @param boolean $destroy Whether to delete the old session or leave it to garbage collection.
     *
     * @return boolean True if session migrated, false if error.
     *
     * @api
     */
    function migrate($destroy = false);

    /**
     * Force the session to be saved and closed.
     *
     * This method is generally not required for real sessions as
     * the session will be automatically saved at the end of
     * code execution.
     */
    function save();

    /**
     * Checks if an attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return Boolean true if the attribute is defined, false otherwise
     */
    function has($name);

    /**
     * Returns an attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The default value if not found.
     *
     * @return mixed
     */
    function get($name, $default = null);

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     */
    function set($name, $value);

    /**
     * Returns attributes.
     *
     * @return array Attributes
     */
    function all();

    /**
     * Sets attributes.
     *
     * @param array $attributes Attributes
     */
    function replace(array $attributes);

    /**
     * Removes an attribute.
     *
     * @param string $name
     */
    function remove($name);

    /**
     * Clears all attributes.
     */
    function clear();
}
