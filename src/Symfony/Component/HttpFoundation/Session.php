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
     * Flag if session has started.
     * 
     * @var boolean
     */
    protected $started;
    
    /**
     * Array of attributes.
     * 
     * @var array
     */
    protected $attributes;
    
    /**
     * Flash message container.
     * 
     * @var \Symfony\Component\HttpFoundation\FlashBagInterface
     */
    protected $flashBag;
    
    /**
     * Flag if session has terminated.
     * 
     * @var boolean
     */
    protected $closed;
    
    /**
     * Constructor.
     *
     * @param SessionStorageInterface $storage  A SessionStorageInterface instance.
     * @param FlashBagInterface       $flashBag A FlashBagInterface instance.
     */
    public function __construct(SessionStorageInterface $storage, FlashBagInterface $flashBag)
    {
        $this->storage = $storage;
        $this->flashBag = $flashBag;
        $this->attributes = array();
        $this->started = false;
        $this->closed = false;
    }
    
    /**
     * Get the flashbag driver.
     * 
     * @return FlashBagInterface
     */
    public function getFlashBag()
    {
        if (false === $this->started) {
            $this->start();
        }
        
        return $this->flashBag;
    }

    /**
     * Starts the session storage.
     *
     * @api
     */
    public function start()
    {
        if (true === $this->started) {
            return;
        }

        $this->storage->start();

        $attributes = $this->storage->read('_symfony2');

        if (isset($attributes['attributes'])) {
            $this->attributes = $attributes['attributes'];
            $this->flashBag->initialize($attributes['flashes']);
        }

        $this->started = true;
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
    public function has($name, $namespace = '/')
    {
        $attributes = $this->resolveAttributePath($namespace);
        
        return array_key_exists($name, $attributes);
    }

    /**
     * Returns an attribute.
     *
     * @param string $name      The attribute name
     * @param mixed  $default   The default value
     * @param string $namespace Namespace
     *
     * @return mixed
     *
     * @api
     */
    public function get($name, $default = null, $namespace = '/')
    {
        $attributes = $this->resolveAttributePath($namespace);
        
        return array_key_exists($name, $attributes) ? $attributes[$name] : $default;
    }

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     * @param string $namespace
     *
     * @api
     */
    public function set($name, $value, $namespace = '/')
    {
        if (false === $this->started) {
            $this->start();
        }
        
        $attributes = & $this->resolveAttributePath($namespace);
        $attributes[$name] = $value;
    }
    
    /**
     * Resolves a path in attributes property and returns it as a reference.
     * 
     * This method allows structured namespacing of session attributes.
     * 
     * @param string $namespace
     * 
     * @return array 
     */
    private function &resolveAttributePath($namespace)
    {
        $array = & $this->attributes;
        $namespace = (strpos($namespace, '/') === 0) ? substr($namespace, 1) : $namespace;
        
        // Check if there is anything to do, else return
        if (!$namespace) {
            return $array;
        }
        
        $parts = explode('/', $namespace);

        foreach ($parts as $part) {
            if (!array_key_exists($part, $array)) {
                $array[$part] = array();
            }

            $array = & $array[$part];
        }
        
        return $array;
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
        return $this->attributes;
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
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes = $attributes;
    }

    /**
     * Removes an attribute.
     *
     * @param string $name
     * @param string $namespace
     *
     * @api
     */
    public function remove($name, $namespace = '/')
    {
        if (false === $this->started) {
            $this->start();
        }
        
        $attributes = & $this->resolveAttributePath($namespace);
        if (array_key_exists($name, $attributes)) {
            unset($attributes[$name]);
        }
    }

    /**
     * Clears all attributes.
     *
     * @api
     */
    public function clear()
    {
        if (false === $this->started) {
            $this->start();
        }

        $this->attributes = array();
        $this->flashBag->clearAll();
    }

    /**
     * Invalidates the current session.
     *
     * @api
     */
    public function invalidate()
    {
        $this->clear();
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
     * @return mixed  The session ID
     *
     * @api
     */
    public function getId()
    {
        if (false === $this->started) {
            $this->start();
        }

        return $this->storage->getId();
    }

    public function save()
    {
        if (false === $this->started) {
            $this->start();
        }

        // expire old flashes
        $this->flashBag->purgeOldFlashes();

        $this->storage->write('_symfony2', array(
            'attributes' => $this->attributes,
            'flashes'    => $this->flashBag->all(),
        ));
    }

    /**
     * This method should be called when you don't want the session to be saved
     * when the Session object is garbaged collected (useful for instance when
     * you want to simulate the interaction of several users/sessions in a single
     * PHP process).
     */
    public function close()
    {
        $this->closed = true;
    }

    public function __destruct()
    {
        if (true === $this->started && !$this->closed) {
            $this->save();
        }
    }

    public function serialize()
    {
        return serialize($this->storage);
    }

    public function unserialize($serialized)
    {
        $this->storage = unserialize($serialized);
        $this->attributes = array();
        $this->started = false;
    }
}
