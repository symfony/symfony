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
 * 
 */
interface FlashBagInterface
{
    /**
     * Initializes the FlashBag.
     * 
     * @param array $flashes 
     */
    public function initialize(array $flashes);

    /**
     * Adds a flash to the stack for a given type.
     */
    public function add($type, $message);
    
    /**
     * Gets flash messages for a given type.
     * 
     * @return array
     */
    public function get($type);
    
    /**
     * Sets an array of flash messages for a given type.
     * 
     * @param string $type
     * @param array  $array 
     */
    public function set($type, array $array);
    
    /**
     * Hass flash messages for a given type?
     * 
     * @return boolean
     */
    public function has($type);

    /**
     * Returns a list of all defined types.
     * 
     * @return array
     */
    public function getTypes();

    /**
     * Gets all flash messages.
     * 
     * @return array
     */
    public function all();
    
    /**
     * Clears flash messages for a given type.
     */
    public function clear($type);
    
    /**
     * Clears all flash messages.
     */
    public function clearAll();
    
    /**
     * Removes flash messages set in a previous request.
     */
    public function purgeOldFlashes();
}
