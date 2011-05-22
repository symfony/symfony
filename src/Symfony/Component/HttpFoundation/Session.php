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

/**
 * Session.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Session implements \Serializable
{
    protected $storage;
    protected $attributes;
    protected $oldFlashes;
    protected $started;
    protected $defaultLocale;

    /**
     * Constructor.
     *
     * @param SessionStorageInterface $storage       A SessionStorageInterface instance
     * @param string                  $defaultLocale The default locale
     */
    public function __construct(SessionStorageInterface $storage, $defaultLocale = 'en')
    {
        $this->storage = $storage;
        $this->defaultLocale = $defaultLocale;
        $this->attributes = array('_flash' => array(), '_locale' => $this->defaultLocale);
        $this->started = false;
    }

    /**
     * Starts the session storage.
     */
    public function start()
    {
        if (true === $this->started) {
            return;
        }

        $this->storage->start();

        $this->attributes = $this->storage->read('_symfony2');

        if (!isset($this->attributes['_flash'])) {
            $this->attributes['_flash'] = array();
        }

        if (!isset($this->attributes['_locale'])) {
            $this->attributes['_locale'] = $this->defaultLocale;
        }

        // flag current flash messages to be removed at shutdown
        $this->oldFlashes = array_flip(array_keys($this->attributes['_flash']));

        $this->started = true;
    }

    /**
     * Checks if an attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return Boolean true if the attribute is defined, false otherwise
     */
    public function has($name)
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Returns an attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The default value
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function set($name, $value)
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes[$name] = $value;
    }

    /**
     * Returns attributes.
     *
     * @return array Attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Sets attributes.
     *
     * @param array $attributes Attributes
     */
    public function setAttributes(array $attributes)
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes = $attributes;
    }

    /**
     * Removes an attribute.
     *
     * @param string $name
     */
    public function remove($name)
    {
        if (false === $this->started) {
            $this->start();
        }

        if (array_key_exists($name, $this->attributes)) {
            unset($this->attributes[$name]);
        }
    }

    /**
     * Clears all attributes.
     */
    public function clear()
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes = array();
    }

    /**
     * Invalidates the current session.
     */
    public function invalidate()
    {
        $this->clear();
        $this->storage->regenerate();
    }

    /**
     * Migrates the current session to a new session id while maintaining all
     * session attributes.
     */
    public function migrate()
    {
        $this->storage->regenerate();
    }

    /**
     * Returns the session ID
     *
     * @return mixed  The session ID
     */
    public function getId()
    {
        return $this->storage->getId();
    }

    /**
     * Returns the locale
     *
     * @return string
     */
    public function getLocale()
    {
        if (!isset($this->attributes['_locale'])) {
            $this->attributes['_locale'] = $this->defaultLocale;
        }

        return $this->attributes['_locale'];
    }

    /**
     * Sets the locale.
     *
     * @param string $locale
     */
    public function setLocale($locale)
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes['_locale'] = $locale;
    }

    public function getFlashes()
    {
        return isset($this->attributes['_flash']) ? $this->attributes['_flash'] : array();
    }

    public function setFlashes($values)
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes['_flash'] = $values;
        $this->oldFlashes = array();
    }

    public function getFlash($name, $default = null)
    {
        return array_key_exists($name, $this->getFlashes()) ? $this->attributes['_flash'][$name] : $default;
    }

    public function setFlash($name, $value)
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes['_flash'][$name] = $value;
        unset($this->oldFlashes[$name]);
    }

    public function hasFlash($name)
    {
        if (false === $this->started) {
            $this->start();
        }

        return array_key_exists($name, $this->attributes['_flash']);
    }

    public function removeFlash($name)
    {
        if (false === $this->started) {
            $this->start();
        }

        unset($this->attributes['_flash'][$name]);
    }

    public function clearFlashes()
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes['_flash'] = array();
        $this->oldFlashes = array();
    }

    public function save()
    {
        if (false === $this->started) {
            $this->start();
        }

        if (isset($this->attributes['_flash'])) {
            $this->attributes['_flash'] = array_diff_key($this->attributes['_flash'], $this->oldFlashes);
        }
        $this->storage->write('_symfony2', $this->attributes);
    }

    public function __destruct()
    {
        if (true === $this->started) {
            $this->save();
        }
    }

    public function serialize()
    {
        return serialize(array($this->storage, $this->defaultLocale));
    }

    public function unserialize($serialized)
    {
        list($this->storage, $this->defaultLocale) = unserialize($serialized);
        $this->attributes = array();
        $this->started = false;
    }
}
