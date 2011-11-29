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

use Symfony\Component\HttpFoundation\SessionStorage\AttributeInterface;
use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * Interface for the session.
 *
 * @author Drak <drak@zikula.org>
 */
interface SessionInterface extends AttributeInterface, \Serializable
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
     * Adds a flash to the stack for a given type.
     *
     * @param string $message
     * @param string $type
     */
    function addFlash($message, $type = FlashBagInterface::NOTICE);

    /**
     * Gets flash messages for a given type.
     *
     * @param string $type Message category type.
     *
     * @return array
     */
    function getFlashes($type = FlashBagInterface::NOTICE);

    /**
     * Pops flash messages off th stack for a given type.
     *
     * @param string $type Message category type.
     *
     * @return array
     */
    function popFlashes($type = FlashBagInterface::NOTICE);

    /**
     * Pop all flash messages from the stack.
     *
     * @return array Empty array or indexed array of arrays.
     */
    function popAllFlashes();

    /**
     * Sets an array of flash messages for a given type.
     *
     * @param string $type
     * @param array  $array
     */
    function setFlashes($type, array $array);

    /**
     * Has flash messages for a given type?
     *
     * @param string $type
     *
     * @return boolean
     */
    function hasFlashes($type);

    /**
     * Returns a list of all defined types.
     *
     * @return array
     */
    function getFlashKeys();

    /**
     * Gets all flash messages.
     *
     * @return array
     */
    function getAllFlashes();

    /**
     * Clears flash messages for a given type.
     *
     * @param string $type
     *
     * @return array Returns an array of what was just cleared.
     */
    function clearFlashes($type);

    /**
     * Clears all flash messages.
     *
     * @return array Array of arrays or array if none.
     */
    function clearAllFlashes();
}
