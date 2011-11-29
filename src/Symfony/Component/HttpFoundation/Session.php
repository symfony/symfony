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

use Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * Session.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class Session implements SessionInterface
{
    /**
     * Storage driver.
     *
     * @var SessionStorageInterface
     */
    protected $storage;

    /**
     * Constructor.
     *
     * @param SessionStorageInterface $storage A SessionStorageInterface instance.
     */
    public function __construct(SessionStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Starts the session storage.
     *
     * @return boolean True if session started.
     *
     * @api
     */
    public function start()
    {
        return $this->storage->start();
    }

    /**
     * Checks if an attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return Boolean true if the attribute is defined, false otherwise
     *
     * @api
     */
    public function has($name)
    {
        return $this->storage->getAttributes()->has($name);
    }

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
    public function get($name, $default = null)
    {
        return $this->storage->getAttributes()->get($name, $default);
    }

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @api
     */
    public function set($name, $value)
    {
        $this->storage->getAttributes()->set($name, $value);
    }

    /**
     * Returns attributes.
     *
     * @return array Attributes
     *
     * @api
     */
    public function all()
    {
        return $this->storage->getAttributes()->all();
    }

    /**
     * Sets attributes.
     *
     * @param array $attributes Attributes
     *
     * @api
     */
    public function replace(array $attributes)
    {
        $this->storage->getAttributes()->replace($attributes);
    }

    /**
     * Removes an attribute.
     *
     * @param string $name
     *
     * @api
     */
    public function remove($name)
    {
        return $this->storage->getAttributes()->remove($name);
    }

    /**
     * Clears all attributes.
     *
     * @api
     */
    public function clear()
    {
        $this->storage->getAttributes()->clear();
    }

    /**
     * Invalidates the current session.
     *
     * Clears all session attributes and flashes and regenerates the
     * session and deletes the old session from persistence.
     *
     * @return boolean True if session invalidated, false if error.
     *
     * @api
     */
    public function invalidate()
    {
        $this->storage->clear();

        return $this->storage->regenerate(true);
    }

    /**
     * Migrates the current session to a new session id while maintaining all
     * session attributes.
     *
     * @param boolean $destroy Whether to delete the old session or leave it to garbage collection.
     *
     * @return boolean True if session migrated, false if error
     *
     * @api
     */
    public function migrate($destroy = false)
    {
        return $this->storage->regenerate($destroy);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->storage->save();
    }

    /**
     * Returns the session ID
     *
     * @return mixed The session ID
     *
     * @api
     */
    public function getId()
    {
        return $this->storage->getId();
    }

    /**
     * Implements the \Serialize interface.
     *
     * @return SessionStorageInterface
     */
    public function serialize()
    {
        return serialize($this->storage);
    }

    /**
     * Implements the \Serialize interface.
     *
     * @throws \InvalidArgumentException If the passed string does not unserialize to an instance of SessionStorageInterface
     */
    public function unserialize($serialized)
    {
        $storage = unserialize($serialized);
        if (!$storage instanceof SessionStorageInterface) {
            throw new \InvalidArgumentException('Serialized data did not return a valid instance of SessionStorageInterface');
        }

        $this->storage = $storage;
    }

    /**
     * Adds a flash to the stack for a given type.
     *
     * @param string $message
     * @param string $type
     */
    public function addFlash($message, $type = FlashBagInterface::NOTICE)
    {
        $this->storage->getFlashes()->add($message, $type);
    }

    /**
     * Gets flash messages for a given type.
     *
     * @param string  $type Message category type.
     *
     * @return array
     */
    public function getFlashes($type = FlashBagInterface::NOTICE)
    {
        return $this->storage->getFlashes()->get($type);
    }

    /**
     * Pops flash messages off th stack for a given type.
     *
     * @param string $type Message category type.
     *
     * @return array
     */
    public function popFlashes($type = FlashBagInterface::NOTICE)
    {
        return $this->storage->getFlashes()->pop($type);
    }

    /**
     * Pop all flash messages from the stack.
     *
     * @return array Empty array or indexed array of arrays.
     */
    public function popAllFlashes()
    {
        return $this->storage->getFlashes()->popAll();
    }

    /**
     * Sets an array of flash messages for a given type.
     *
     * @param string $type
     * @param array  $array
     */
    public function setFlashes($type, array $array)
    {
        $this->storage->getFlashes()->set($type, $array);
    }

    /**
     * Has flash messages for a given type?
     *
     * @param string $type
     *
     * @return boolean
     */
    public function hasFlashes($type)
    {
        return $this->storage->getFlashes()->has($type);
    }

    /**
     * Returns a list of all defined types.
     *
     * @return array
     */
    public function getFlashKeys()
    {
        return $this->storage->getFlashes()->keys();
    }

    /**
     * Gets all flash messages.
     *
     * @return array
     */
    public function getAllFlashes()
    {
        return $this->storage->getFlashes()->all();
    }

    /**
     * Clears flash messages for a given type.
     *
     * @param string $type
     *
     * @return array Returns an array of what was just cleared.
     */
    public function clearFlashes($type)
    {
        return $this->storage->getFlashes()->clear($type);
    }

    /**
     * Clears all flash messages.
     *
     * @return array Empty array or indexed arrays or array if none.
     */
    public function clearAllFlashes()
    {
        return $this->storage->getFlashes()->clearAll();
    }
}
