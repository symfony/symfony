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
     * @param SessionStorageInterface $storage  A SessionStorageInterface instance.
     */
    public function __construct(SessionStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Starts the session storage.
     *
     * @api
     */
    public function start()
    {
        $this->storage->start();
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
        return $this->storage->has($name);
    }

    /**
     * Returns an attribute.
     *
     * @param string $name      The attribute name
     * @param mixed  $default   The default value
     *
     * @return mixed
     *
     * @api
     */
    public function get($name, $default = null)
    {
        return $this->storage->get($name, $default);
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
        $this->storage->set($name, $value);
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
        return $this->storage->all();
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
        $this->storage->replace($attributes);
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
        $this->storage->remove($name);
    }

    /**
     * Clears all attributes.
     *
     * @api
     */
    public function clear()
    {
        $this->storage->clear();
    }

    /**
     * Invalidates the current session.
     *
     * @api
     */
    public function invalidate()
    {
        $this->storage->regenerate(true);
    }

    /**
     * Migrates the current session to a new session id while maintaining all
     * session attributes.
     *
     * @api
     */
    public function migrate()
    {
        $this->storage->regenerate();
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
     * Gets the flash messages driver.
     *
     * @return FlashBagInterface
     */
    public function getFlashBag()
    {
        return $this->storage->getFlashBag();
    }

    /**
     * Adds a flash to the stack for a given type.
     *
     * @param string $message
     * @param string $type
     */
    function flashAdd($message, $type = FlashBagInterface::NOTICE)
    {
        $this->storage->getFlashBag()->add($message, $type);
    }

    /**
     * Gets flash messages for a given type.
     *
     * @param string  $type  Message category type.
     * @param boolean $clear Clear the messages after get (default true).
     *
     * @return array
     */
    function flashGet($type, $clear = true)
    {
        return $this->storage->getFlashBag()->get($type, $clear);
    }
}
