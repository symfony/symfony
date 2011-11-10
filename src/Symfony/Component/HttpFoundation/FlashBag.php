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
 * FlashBag flash message container.
 */
class FlashBag implements FlashBagInterface
{
    /**
     * Flash messages.
     * 
     * @var array
     */
    private $flashes = array();
    
    /**
     * Old flash messages to be purged.
     * 
     * @var array
     */
    private $oldFlashes = array();
    
    /**
     * @var boolean
     */
    private $initialized = false;
    
    /**
     * Initializes the FlashBag.
     * 
     * @param array $flashes 
     */
    public function initialize(array $flashes)
    {
        if ($this->initialized) {
            return;
        }
        
        $this->flashes = $flashes;
        $this->oldFlashes = $flashes;
        $this->initialized = true;
    }

    /**
     * Adds a flash to the stack for a given type.
     */
    public function add($type, $message)
    {
        $this->flashes[$type][] = $message;
    }
    
    /**
     * Gets flashes for a given type.
     * 
     * @return array
     */
    public function get($type)
    {
        if (!$this->has($type)) {
            throw new \InvalidArgumentException(sprintf('Specified $type %s does not exist', $type));
        }
        
        return $this->flashes[$type];
    }
    
    /**
     * Sets an array of flash messages for a given type.
     * 
     * @param string $type
     * @param array  $array 
     */
    public function set($type, array $array)
    {
        $this->flashes[$type] = $array;
    }
    
    /**
     * Has messages for a given type?
     * 
     * @return boolean
     */
    public function has($type)
    {
        return array_key_exists($type, $this->flashes);
    }
    
    /**
     * Returns a list of all defined types.
     * 
     * @return array
     */
    public function getTypes()
    {
        return array_keys($this->flashes);
    }

    /**
     * Gets all flashes.
     * 
     * @return array
     */
    public function all()
    {
        return $this->flashes;
    }
    
    /**
     * Clears flash messages for a given type.
     */
    public function clear($type)
    {
        if (isset($this->flashes[$type])) {
            unset($this->flashes[$type]);
        }
        
        if (isset($this->oldFlashes[$type])) {
            unset($this->oldFlashes[$type]);
        }
    }
    
    /**
     * Clears all flash messages.
     */
    public function clearAll()
    {
        $this->flashes = array();
        $this->oldFlashes = array();
    }
    
    /**
     * Removes flash messages set in a previous request.
     */
    public function purgeOldFlashes()
    {
        foreach ($this->oldFlashes as $type => $flashes) {
            $this->flashes[$type] = array_diff($this->flashes[$type], $flashes);
        }
    }
    
}
