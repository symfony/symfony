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
 *
 * @author Drak <drak@zikula.org>
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
     * @var boolean
     */
    private $initialized = false;

    /**
     * The storage key for flashes in the session
     *
     * @var string
     */
    private $storageKey;

    /**
     * Constructor.
     *
     * @param type $storageKey The key used to store flashes in the session.
     */
    public function __construct($storageKey = '_sf2_flashes')
    {
        $this->storageKey = $storageKey;
    }

    /**
     * Initializes the FlashBag.
     *
     * @param array $flashes
     */
    public function initialize(array &$flashes)
    {
        if ($this->initialized) {
            return;
        }

        $this->flashes = &$flashes;
        $this->initialized = true;
    }

    /**
     * Adds a flash to the stack for a given type.
     *
     * @param string $message Message.
     * @param string $type    Message category, default NOTICE.
     */
    public function add($message, $type = self::NOTICE)
    {
        $this->flashes[$type][] = $message;
    }

    /**
     * Gets flashes for a given type.
     *
     * @param string  $type  The message category type.
     * @param boolean $clear Whether to clear the messages after return.
     *
     * @return array
     */
    public function get($type, $clear = false)
    {
        if (!$this->has($type)) {
            return array();
        }

        return $clear ? $this->clear($type) : $this->flashes[$type];
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
     * @param boolean $clear Whether to clear all flash messages after return
     *
     * @return array
     */
    public function all($clear = false)
    {
        return $clear ? $this->clearAll() : $this->flashes;
    }

    /**
     * Clears flash messages for a given type.
     *
     * @return array Of whatever was cleared.
     */
    public function clear($type)
    {
        $return = array();
        if (isset($this->flashes[$type])) {
            $return = $this->flashes[$type];
            unset($this->flashes[$type]);
        }

        return $return;
    }

    /**
     * Clears all flash messages.
     *
     * @return array Array of all flashes types.
     */
    public function clearAll()
    {
        $return = $this->flashes;
        $this->flashes = array();

        return $return;
    }

    /**
     * Gets the storage key for this bag.
     *
     * @return string
     */
    function getStorageKey()
    {
        return $this->storageKey;
    }
}
