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
    /**
     * Initializes the FlashBag.
     * 
     * @param array $flashes 
     */
    function initialize(array $flashes);

    /**
     * Adds a flash to the stack for a given type.
     */
    function add($type, $message);
    
    /**
     * Gets flash messages for a given type.
     * 
     * @return array
     */
    function get($type);
    
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
     * @return array
     */
    function all();
    
    /**
     * Clears flash messages for a given type.
     */
    function clear($type);
    
    /**
     * Clears all flash messages.
     */
    function clearAll();
    
    /**
     * Removes flash messages set in a previous request.
     */
    function purgeOldFlashes();
}
