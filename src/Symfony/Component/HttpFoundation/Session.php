<?php

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Session.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Session implements \Serializable
{
    protected $storage;
    protected $locale;
    protected $attributes;
    protected $oldFlashes;
    protected $started;
    protected $options;

    /**
     * Constructor.
     *
     * @param SessionStorageInterface $session A SessionStorageInterface instance
     * @param array                   $options An array of options
     */
    public function __construct(SessionStorageInterface $storage, array $options = array())
    {
        $this->storage = $storage;
        $this->options = $options;
        $this->attributes = array();
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
            $this->attributes['_locale'] = isset($this->options['default_locale']) ? $this->options['default_locale'] : 'en';
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
        if (false === $this->started) {
            $this->start();
        }

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
        if (false === $this->started) {
            $this->start();
        }

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
        if (false === $this->started) {
            $this->start();
        }

        return $this->attributes;
    }

    /**
     * Sets attributes.
     *
     * @param array $attributes Attributes
     */
    public function setAttributes($attributes)
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
        if (array_key_exists($this->attributes, $name)) {
            if (false === $this->started) {
                $this->start();
            }

            unset($this->attributes[$name]);
        }
    }

    /**
     * Returns the locale
     *
     * @return string
     */
    public function getLocale()
    {
        if (false === $this->started) {
            $this->start();
        }

        return $this->getAttribute('_locale');
    }

    /**
     * Sets the locale.
     *
     * @param string $locale
     */
    public function setLocale($locale)
    {
        if ($this->locale != $locale) {
            $this->setAttribute('_locale', $locale);
        }
    }

    public function getFlashMessages()
    {
        if (false === $this->started) {
            $this->start();
        }

        return $this->attributes['_flash'];
    }

    public function setFlashMessages($values)
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes['_flash'] = $values;
    }

    public function getFlash($name, $default = null)
    {
        if (false === $this->started) {
            $this->start();
        }

        return array_key_exists($name, $this->attributes['_flash']) ? $this->attributes['_flash'][$name] : $default;
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

    public function save()
    {
        if (true === $this->started) {
            $this->attributes['_flash'] = array_diff_key($this->attributes['_flash'], $this->oldFlashes);
            $this->storage->write('_symfony2', $this->attributes);
        }
    }

    public function __destruct()
    {
        $this->save();
    }

    public function serialize()
    {
        return serialize(array($this->storage, $this->options));
    }

    public function unserialize($serialized)
    {
        list($this->storage, $this->options) = unserialize($serialized);
        $this->attributes = array();
        $this->started = false;
    }
}
