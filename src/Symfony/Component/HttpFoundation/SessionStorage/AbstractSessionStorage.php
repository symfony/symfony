<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\FlashBag;
use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * This provides a base class for session attribute storage.
 */
abstract class AbstractSessionStorage implements SessionStorageInterface
{
    /**
     * @var array
     */
    protected $attributes = array();
    
    /**
     * @var array
     */
    protected $options;
    
    /**
     * @var \Symfony\Component\HttpFoundation\FlashBagInterface
     */
    protected $flashBag;
    
    /**
     * @var boolean
     */
    protected $started = false;
    
    /**
     * Constructor.
     * 
     * Depending on how you want the storage driver to behave you probably
     * want top override this constructor entirely.
     * 
     * List of options for $options array with their defaults.
     * 
     * auto_start	"0"
     * cookie_domain	""
     * cookie_httponly	""
     * cookie_lifetime	"0"
     * cookie_path	"/"
     * cookie_secure	""
     * entropy_file	""
     * entropy_length	"0"
     * gc_divisor	"100"
     * gc_maxlifetime	"1440"
     * gc_probability	"1"
     * hash_bits_per_character	"4"
     * hash_function	"0"
     * name	"PHPSESSID"
     * referer_check	""
     * save_path	""
     * serialize_handler	"php"
     * use_cookies	"1"
     * use_only_cookies	"1"
     * use_trans_sid	"0"
     * 
     * @param FlashBagInterface $flashBag
     * @param array             $options
     */
    public function __construct(FlashBagInterface $flashBag, array $options = array())
    {
        $this->flashBag = $flashBag;
        $this->setOptions($options);
        $this->registerSaveHandlers();
        $this->registerShutdownFunction();
    }
    
    /**
     * Gets the flashbag.
     * 
     * @return FlashBagInterface 
     */
    public function getFlashBag()
    {
        if (!$this->started) {
            $this->start();
        }
        
        return $this->flashBag;
    }
    
    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started) {
            // session is already started.
            return;
        }
        
        // sanity check to make sure session was not started elsewhere
        if (session_id()) {
            throw new \RuntimeException('The session was already started outside of [HttpFoundation]');
        }
        
        // disable native cache limiter as this is managed by HeaderBag directly
        session_cache_limiter(false);
        
        // generate random session ID
        if (!session_id()) {
            session_id(sha1(uniqid(mt_rand(), true)));
        }
        
        // start the session
        if (!session_start()) {
            throw new \RuntimeException('Failed to start the session');
        }
        
        // after starting the session, PHP retrieves the session from whatever handlers were set
        // either PHP's internal, or the ones we set using sssion_set_save_handler().  PHP takes
        // the return value from the sessionRead() handler and populates $_SESSION with it automatically.
        $_SESSION[self::STORAGE_KEY] = isset($_SESSION[self::STORAGE_KEY]) ? $_SESSION[self::STORAGE_KEY] : array();
        $this->attributes = & $_SESSION[self::STORAGE_KEY];
        
        $_SESSION[FlashBagInterface::STORAGE_KEY] = isset($_SESSION[FlashBagInterface::STORAGE_KEY]) ? $_SESSION[FlashBagInterface::STORAGE_KEY] : array();
        $this->flashBag->initialize($_SESSION[FlashBagInterface::STORAGE_KEY]);
        
        $this->started = true;
    }
     
    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        if (!$this->started) {
            return ''; // returning empty is consistent with session_id() behaviour
        }
        
        return session_id();
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
        if (!$this->started) {
            $this->start();
        }

        $attributes = $this->resolveAttributePath($name);
        $name = $this->resolveKey($name);
        
        return array_key_exists($name, $attributes);
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
        if (!$this->started) {
            $this->start();
        }
        
        $attributes = $this->resolveAttributePath($name);
        $name = $this->resolveKey($name);
        
        return array_key_exists($name, $attributes) ? $attributes[$name] : $default;
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
        if (!$this->started) {
            $this->start();
        }
        
        $attributes = & $this->resolveAttributePath($name, true);
        $name = $this->resolveKey($name);
        $attributes[$name] = $value;
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
        if (!$this->started) {
            $this->start();
        }
        
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
        if (!$this->started) {
            $this->start();
        }
        
        $this->attributes = array();
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Removes an attribute.
     *
     * @param string $name
     * 
     * @return mixed
     *
     * @api
     */
    public function remove($name)
    {
        if (!$this->started) {
            $this->start();
        }
        
        $retval = null;
        $attributes = & $this->resolveAttributePath($name);
        $name = $this->resolveKey($name);
        if (array_key_exists($name, $attributes)) {
            $retval = $attributes[$name];
            unset($attributes[$name]);
        }
        return $retval;
    }

    /**
     * Clears all attributes.
     *
     * @api
     */
    public function clear()
    {
        if (!$this->started) {
            $this->start();
        }
        
        $this->attributes = array();
    }
    
    /**
     * Regenerates the session.
     * 
     * This method will regenerate the session ID and optionally
     * destroy the old ID.  Session regeneration should be done
     * periodically and for example, should be done when converting
     * an anonymous session to a logged in user session.
     * 
     * @param boolean $destroy
     * 
     * @return boolean Returns true on success or false on failure.
     */
    public function regenerate($destroy = false)
    {
        return session_regenerate_id($destroy);
    }
    
    /**
     * Sets the session.* ini variables.
     * 
     * Note we omit session. from the beginning of the keys.
     * 
     * @param array $options 
     */
    protected function setOptions(array $options)
    {
        $cookieDefaults = session_get_cookie_params();
        $this->options = array_merge(array(
            'lifetime' => $cookieDefaults['lifetime'],
            'path'     => $cookieDefaults['path'],
            'domain'   => $cookieDefaults['domain'],
            'secure'   => $cookieDefaults['secure'],
            'httponly' => isset($cookieDefaults['httponly']) ? $cookieDefaults['httponly'] : false,
        ), $options);
        
        // See session.* for values at http://www.php.net/manual/en/ini.list.php
        foreach ($this->options as $key => $value) {
            ini_set('session.'.$key, $value);
        }
    }
    
    /**
     * Registers this storage device for PHP session handling.
     * 
     * PHP requires session save handlers to be set, either it's own, or custom ones.  
     * There are some defaults set automatically when PHP starts, but these can be overriden 
     * using this command if you need anything other than PHP's default handling.
     * 
     * When the session starts, PHP will call the sessionRead() handler which should return an array
     * of any session attributes.  PHP will then populate these into $_SESSION.
     * 
     * When PHP shuts down, the sessionWrite() handler is called and will pass the $_SESSION contents
     * to be stored.
     * 
     * When a session is specifically destroyed, PHP will call the sessionDestroy() handler with the
     * session ID.  This happens when the session is regenerated for example and th handler 
     * MUST delete the session by ID from the persistent storage immediately.
     * 
     * PHP will call sessionGc() from time to time to expire any session records according to the
     * set max lifetime of a session.  This routine should delete all records from persistent
     * storage which were last accessed longer than the $lifetime.
     * 
     * PHP sessionOpen() and sessionClose() are pretty much redundant and can just return true.
     * 
     * @see http://php.net/manual/en/function.session-set-save-handler.php
     * @see SessionSaveHandlerInterface 
     */
    protected function registerSaveHandlers()
    {
        // note this can be reset to PHP's control using ini_set('session.save_handler', 'files');
        // so long as ini_set() is called before the session is started.
        if ($this instanceof SessionSaveHandlerInterface) {
            session_set_save_handler(
                    array($this, 'sessionOpen'), 
                    array($this, 'sessionClose'), 
                    array($this, 'sessionRead'), 
                    array($this, 'sessionWrite'), 
                    array($this, 'sessionDestroy'), 
                    array($this, 'sessionGc')
                    );
        }
    }
    
    /**
     * Registers PHP shutdown function.
     * 
     * This methos is required to avoid strange issues when using PHP objects as 
     * session save handlers.
     */
    protected function registerShutdownFunction()
    {
        register_shutdown_function('session_write_close');
    }
    
    /**
     * Resolves a path in attributes property and returns it as a reference.
     * 
     * This method allows structured namespacing of session attributes.
     * 
     * @param string  $name
     * @param boolean $writeContext
     * 
     * @return array 
     */
    protected function &resolveAttributePath($name, $writeContext = false)
    {
        $array = & $this->attributes;
        $name = (strpos($name, '.') === 0) ? substr($name, 1) : $name;
        
        // Check if there is anything to do, else return
        if (!$name) {
            return $array;
        }
        
        $parts = explode('.', $name);
        if (count($parts) < 2) {
            if (!$writeContext) {
                return $array;
            }
            $array[$parts[0]] = array();
            return $array;
        }
        unset($parts[count($parts)-1]);

        foreach ($parts as $part) {
            if (!array_key_exists($part, $array)) {
                if (!$writeContext) {
                    return $array;
                }
                $array[$part] = array();
            }

            $array = & $array[$part];
        }
        
        return $array;
    }
    
    /**
     * Resolves the key from the name.
     * 
     * This is the last part in a dot separated string.
     * 
     * @param string $name
     * 
     * @return string
     */
    protected function resolveKey($name)
    {
        if (strpos($name, '.') !== false) {
            $name = substr($name, strrpos($name, '.')+1, strlen($name));
        }
        
        return $name;
    }
}
