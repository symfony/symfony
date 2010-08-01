<?php

namespace Symfony\Components\HttpFoundation;

use Symfony\Components\HttpFoundation\SessionStorage\SessionStorageInterface;

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
class Session
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
    public function __construct(SessionStorageInterface $storage, $options = array())
    {
        $this->storage = $storage;
        $this->options = $options;
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

        $this->setAttributes($this->storage->read('_symfony2', array(
            '_flash'   => array(),
            '_locale'  => isset($this->options['default_locale']) ? $this->options['default_locale'] : 'en',
        )));

        // flag current flash to be removed at shutdown
        $this->oldFlashes = array_flip(array_keys($this->getFlashMessages()));

        $this->started = true;
    }

    /**
     * Returns an attribute
     *
     * @param string $name    The attribute name
     * @param mixed  $default The default value
     *
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function setAttribute($name, $value)
    {
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
     * Sets attributes
     *
     * @param array $attributes Attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Returns the locale
     *
     * @return string
     */
    public function getLocale()
    {
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
        return $this->attributes['_flash'];
    }

    public function setFlashMessages($values)
    {
        $this->attributes['_flash'] = $values;
    }

    public function getFlash($name, $default = null)
    {
        return $this->hasFlash($name) ? $this->attributes['_flash'][$name] : $default;
    }

    public function setFlash($name, $value)
    {
        $this->attributes['_flash'][$name] = $value;
        unset($this->oldFlashes[$name]);
    }

    public function hasFlash($name)
    {
        return array_key_exists($name, $this->attributes['_flash']);
    }

    public function __destruct()
    {
        if (true === $this->started) {
            $this->attributes['_flash'] = array_diff_key($this->attributes['_flash'], $this->oldFlashes);

            $this->storage->write('_symfony2', $this->attributes);
        }
    }
}
