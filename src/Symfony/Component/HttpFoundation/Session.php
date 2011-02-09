<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface;

/**
 * Session.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Session implements \Serializable
{
    protected $storage;
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
        $this->attributes = array('_flash' => array(), '_locale' => $this->getDefaultLocale());
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
            $this->attributes['_locale'] = $this->getDefaultLocale();
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
        return $this->attributes['_flash'];
    }

    public function setFlashes($values)
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes['_flash'] = $values;
    }

    public function getFlash($name, $default = null)
    {
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
        return array_key_exists($name, $this->attributes['_flash']);
    }

    public function removeFlash($name)
    {
        unset($this->attributes['_flash'][$name]);
    }

    public function clearFlashes()
    {
        $this->attributes['_flash'] = array();
    }

    public function save()
    {
        if (true === $this->started) {
            if (isset($this->attributes['_flash'])) {
                $this->attributes['_flash'] = array_diff_key($this->attributes['_flash'], $this->oldFlashes);
            }
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

    protected function getDefaultLocale()
    {
        return isset($this->options['default_locale']) ? $this->options['default_locale'] : 'en';
    }
}
