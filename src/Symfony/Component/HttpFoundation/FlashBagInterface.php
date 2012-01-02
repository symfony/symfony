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
 * FlashBagInterface.
 *
 * @author Drak <drak@zikula.org>
 */
interface FlashBagInterface extends SessionBagInterface
{
    const INFO = 'info';
    const NOTICE = 'notice';
    const WARNING = 'warning';
    const ERROR = 'error';

    /**
     * Adds a flash to the stack for a given type.
     *
     * @param string $message
     * @param string $type
     */
    function add($message, $type = self::NOTICE);

    /**
     * Gets flash messages for a given type.
     *
     * @param string  $type  Message category type.
     *
     * @return array
     */
    function get($type);

    /**
     * Pops and clears flashes from the stack.
     *
     * @param string $type
     *
     * @return array
     */
    function pop($type);

    /**
     * Pops all flashes from the stack and clears flashes.
     *
     * @param string $type
     *
     * @return array Empty array, or indexed array of arrays.
     */
    function popAll();

    /**
     * Sets an array of flash messages for a given type.
     *
     * @param string $type
     * @param array  $array
     */
    function set($type, array $array);

    /**
     * Has flash messages for a given type?
     *
     * @param string $type
     *
     * @return boolean
     */
    function has($type);

    /**
     * Returns a list of all defined types.
     *
     * @return array
     */
    function keys();

    /**
     * Gets all flash messages.
     *
     * @return array
     */
    function all();

    /**
     * Clears flash messages for a given type.
     *
     * @param string $type
     *
     * @return array Returns an array of what was just cleared.
     */
    function clear($type);

    /**
     * Clears all flash messages.
     *
     * @return array Empty array or indexed arrays or array if none.
     */
    function clearAll();
}
