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
     * Gets all flash messages.
     *
     * @return FlashBagInterface
     */
    public function getFlashes()
    {
        return $this->storage->getFlashes();
    }
}
