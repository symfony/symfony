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
interface FlashBagInterface
{
    const STORAGE_KEY = '_sf2_flashes';
    const STATUS = 'status';
    const ERROR = 'error';
    
    /**
     * Initializes the FlashBag.
     * 
     * @param array $flashes 
     */
    function initialize(array &$flashes);

    /**
     * Adds a flash to the stack for a given type.
     */
    function add($message, $type);
    
    /**
     * Gets flash messages for a given type.
     * 
     * @param string  $type  Message category type.
     * @param boolean $clear Clear the messages after return (default false).
     * 
     * @return array
     */
    function get($type, $clear = false);
    
    /**
     * Sets an array of flash messages for a given type.
     * 
     * @param string $type
     * @param array  $array 
     */
    function set($type, array $array);
    
    /**
     * Hass flash messages for a given type?
     * 
     * @return boolean
     */
    function has($type);

    /**
     * Returns a list of all defined types.
     * 
     * @return array
     */
    function getTypes();

    /**
     * Gets all flash messages.
     * 
     * @param boolean $clear Clear the messages after return (default false).
     * 
     * @return array
     */
    function all($clear = false);
    
    /**
     * Clears flash messages for a given type.
     * 
     * @return array Returns an array of what was just cleared.
     */
    function clear($type);
    
    /**
     * Clears all flash messages.
     * 
     * @return array Array of arrays or array if none.
     */
    function clearAll();
}