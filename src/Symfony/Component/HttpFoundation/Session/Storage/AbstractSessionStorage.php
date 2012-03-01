<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

/**
 * This provides a base class for session attribute storage.
 *
 * This can be used to implement internal PHP session handlers
 * provided by PHP extensions or custom session save handlers
 * implementing the \SessionHandlerInterface
 *
 * @see http://php.net/session.customhandler
 * @see http://php.net/sessionhandlerinterface
 *
 * @author Drak <drak@zikula.org>
 */
abstract class AbstractSessionStorage implements SessionStorageInterface
{
    /**
     * Array of SessionBagInterface
     *
     * @var array
     */
    protected $bags;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var boolean
     */
    protected $started = false;

    /**
     * @var boolean
     */
    protected $closed = false;

    /**
     * Constructor.
     *
     * Depending on how you want the storage driver to behave you probably
     * want top override this constructor entirely.
     *
     * List of options for $options array with their defaults.
     * @see http://php.net/session.configuration for options
     * but we omit 'session.' from the beginning of the keys for convenience.
     *
     * auto_start, "0"
     * cache_limiter, "nocache" (use "0" to prevent headers from being sent entirely).
     * cookie_domain, ""
     * cookie_httponly, ""
     * cookie_lifetime, "0"
     * cookie_path, "/"
     * cookie_secure, ""
     * entropy_file, ""
     * entropy_length, "0"
     * gc_divisor, "100"
     * gc_maxlifetime, "1440"
     * gc_probability, "1"
     * hash_bits_per_character, "4"
     * hash_function, "0"
     * name, "PHPSESSID"
     * referer_check, ""
     * save_path, ""
     * serialize_handler, "php"
     * use_cookies, "1"
     * use_only_cookies, "1"
     * use_trans_sid, "0"
     * upload_progress.enabled, "1"
     * upload_progress.cleanup, "1"
     * upload_progress.prefix, "upload_progress_"
     * upload_progress.name, "PHP_SESSION_UPLOAD_PROGRESS"
     * upload_progress.freq, "1%"
     * upload_progress.min-freq, "1"
     * url_rewriter.tags, "a=href,area=href,frame=src,form=,fieldset="
     *
     * @param array $options Session configuration options.
     */
    public function __construct(array $options = array())
    {
        $this->setOptions($options);
        $this->registerSaveHandlers();
        $this->registerShutdownFunction();
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        if ($this->options['use_cookies'] && headers_sent()) {
            throw new \RuntimeException('Failed to start the session because header have already been sent.');
        }

        // start the session
        if (!session_start()) {
            throw new \RuntimeException('Failed to start the session');
        }

        $this->loadSession();

        $this->started = true;
        $this->closed = false;

        return true;
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
     * {@inheritdoc}
     */
    public function regenerate($destroy = false)
    {
        return session_regenerate_id($destroy);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        session_write_close();
        $this->closed = true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        // clear out the bags
        foreach ($this->bags as $bag) {
            $bag->clear();
        }

        // clear out the session
        $_SESSION = array();

        // reconnect the bags to the session
        $this->loadSession();
    }

    /**
     * {@inheritdoc}
     */
    public function registerBag(SessionBagInterface $bag)
    {
        $this->bags[$bag->getName()] = $bag;
    }

    /**
     * {@inheritdoc}
     */
    public function getBag($name)
    {
        if (!isset($this->bags[$name])) {
            throw new \InvalidArgumentException(sprintf('The SessionBagInterface %s is not registered.', $name));
        }

        if ($this->options['auto_start'] && !$this->started) {
            $this->start();
        }

        return $this->bags[$name];
    }

    /**
     * Sets session.* ini variables.
     *
     * For convenience we omit 'session.' from the beginning of the keys.
     * Explicitly ignores other ini keys.
     *
     * session_get_cookie_params() overrides values.
     *
     * @param array $options
     *
     * @see http://php.net/session.configuration
     */
    protected function setOptions(array $options)
    {
        $this->options = $options;

        // set defaults for certain values
        $defaults = array(
            'cache_limiter' => '', // disable by default because it's managed by HeaderBag (if used)
            'auto_start' => false,
            'use_cookies' => true,
            'cookie_httponly' => true,
        );

        foreach ($defaults as $key => $value) {
            if (!isset($this->options[$key])) {
                $this->options[$key] = $value;
            }
         }

        foreach ($this->options as $key => $value) {
            if (in_array($key, array(
                'auto_start', 'cache_limiter', 'cookie_domain', 'cookie_httponly',
                'cookie_lifetime', 'cookie_path', 'cookie_secure',
                'entropy_file', 'entropy_length', 'gc_divisor',
                'gc_maxlifetime', 'gc_probability', 'hash_bits_per_character',
                'hash_function', 'name', 'referer_check',
                'save_path', 'serialize_handler', 'use_cookies',
                'use_only_cookies', 'use_trans_sid', 'upload_progress.enabled',
                'upload_progress.cleanup', 'upload_progress.prefix', 'upload_progress.name',
                'upload_progress.freq', 'upload_progress.min-freq', 'url_rewriter.tags'))) {
                ini_set('session.'.$key, $value);
            }
        }
    }

    /**
     * Registers this storage device as a PHP session handler.
     *
     * To use internal PHP session save handlers, override this method using ini_set with
     * session.save_handlers and session.save_path e.g.
     *
     *     ini_set('session.save_handlers', 'files');
     *     ini_set('session.save_path', /tmp');
     *
     * @see http://php.net/session-set-save-handler
     * @see http://php.net/sessionhandlerinterface
     */
    protected function registerSaveHandlers()
    {
        // note this can be reset to PHP's control using ini_set('session.save_handler', 'files');
        // so long as ini_set() is called before the session is started.
        if ($this instanceof \SessionHandlerInterface) {
            session_set_save_handler(
                array($this, 'open'),
                array($this, 'close'),
                array($this, 'read'),
                array($this, 'write'),
                array($this, 'destroy'),
                array($this, 'gc')
            );
        }
    }

    /**
     * Registers PHP shutdown function.
     *
     * This method is required to avoid strange issues when using PHP objects as
     * session save handlers.
     *
     * @see http://php.net/register-shutdown-function
     */
    protected function registerShutdownFunction()
    {
        register_shutdown_function('session_write_close');
    }

    /**
     * Load the session with attributes.
     *
     * After starting the session, PHP retrieves the session from whatever handlers
     * are set to (either PHP's internal, or a custom save handler set with session_set_save_handler()).
     * PHP takes the return value from the read() handler, unserializes it
     * and populates $_SESSION with the result automatically.
     *
     * @param array|null $session
     */
    protected function loadSession(array &$session = null)
    {
        if (null === $session) {
            $session = &$_SESSION;
        }

        foreach ($this->bags as $bag) {
            $key = $bag->getStorageKey();
            $session[$key] = isset($session[$key]) ? $session[$key] : array();
            $bag->initialize($session[$key]);
        }
    }
}
