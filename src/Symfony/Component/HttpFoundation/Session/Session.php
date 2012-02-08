<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

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
     * @param AttributeBagInterface   $attributes An AttributeBagInterface instance, (defaults null for default AttributeBag)
     * @param FlashBagInterface       $flashes    A FlashBagInterface instance (defaults null for default FlashBag)
     */
    public function __construct(SessionStorageInterface $storage, AttributeBagInterface $attributes = null, FlashBagInterface $flashes = null)
    {
        $this->storage = $storage;
        $this->registerBag($attributes ?: new AttributeBag());
        $this->registerBag($flashes ?: new FlashBag());
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
        return $this->storage->getBag('attributes')->has($name);
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
        return $this->storage->getBag('attributes')->get($name, $default);
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
        $this->storage->getBag('attributes')->set($name, $value);
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
        return $this->storage->getBag('attributes')->all();
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
        $this->storage->getBag('attributes')->replace($attributes);
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
        return $this->storage->getBag('attributes')->remove($name);
    }

    /**
     * Clears all attributes.
     *
     * @api
     */
    public function clear()
    {
        $this->storage->getBag('attributes')->clear();
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

    public function registerBag(SessionBagInterface $bag)
    {
        $this->storage->registerBag($bag);
    }

    public function getBag($name)
    {
        return $this->storage->getBag($name);
    }

    /**
     * Gets the flashbag interface.
     *
     * @return FlashBagInterface
     */
    public function getFlashes()
    {
        return $this->getBag('flashes');
    }
}
