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
     * @see http://www.php.net/manual/en/session.configuration.php for options
     * but we omit 'session.' from the beginning of the keys.
     *
     * auto_start, "0"
     * cache_limiter, ""
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
     * @param array                 $options    Session configuration options.
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
     * @see http://www.php.net/manual/en/session.configuration.php
     */
    protected function setOptions(array $options)
    {
        $cookieDefaults = session_get_cookie_params();
        $this->options = array_merge(array(
            'cookie_lifetime' => $cookieDefaults['lifetime'],
            'cookie_path' => $cookieDefaults['path'],
            'cookie_domain' => $cookieDefaults['domain'],
            'cookie_secure' => $cookieDefaults['secure'],
            'cookie_httponly' => isset($cookieDefaults['httponly']) ? $cookieDefaults['httponly'] : false,
            ), $options);

        // Unless session.cache_limiter has been set explicitly, disable it
        // because this is managed by HeaderBag directly (if used).
        if (!isset($this->options['cache_limiter'])) {
            $this->options['cache_limiter'] = false;
        }

        if (!isset($this->options['auto_start'])) {
            $this->options['auto_start'] = 0;
        }

        if (!isset($this->options['use_cookies'])) {
            $this->options['use_cookies'] = 1;
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
     * Registers this storage device for PHP session handling.
     *
     * PHP requires session save handlers to be set, either it's own, or custom ones.
     * There are some defaults set automatically when PHP starts, but these can be overriden
     * using this command if you need anything other than PHP's default handling.
     *
     * When the session starts, PHP will call the sessionRead() handler which should return an array
     * of any session attributes. PHP will then populate these into $_SESSION.
     *
     * When PHP shuts down, the write() handler is called and will pass the $_SESSION contents
     * to be stored.
     *
     * When a session is specifically destroyed, PHP will call the destroy() handler with the
     * session ID. This happens when the session is regenerated for example and th handler
     * MUST delete the session by ID from the persistent storage immediately.
     *
     * PHP will call gc() from time to time to expire any session records according to the
     * set max lifetime of a session. This routine should delete all records from persistent
     * storage which were last accessed longer than the $lifetime.
     *
     * PHP open() and close() are pretty much redundant and can just return true.
     *
     * NOTE:
     *
     * To use PHP native save handlers, override this method using ini_set with
     * session.save_handlers and session.save_path e.g.
     *
     *     ini_set('session.save_handlers', 'files');
     *     ini_set('session.save_path', /tmp');
     *
     * @see http://php.net/manual/en/function.session-set-save-handler.php
     * @see \SessionHandlerInterface
     * @see http://php.net/manual/en/class.sessionhandlerinterface.php
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
     */
    protected function registerShutdownFunction()
    {
        register_shutdown_function('session_write_close');
    }

    /**
     * Load the session with attributes.
     *
     * After starting the session, PHP retrieves the session from whatever handlers
     * are set to (either PHP's internal, custom set with session_set_save_handler()).
     * PHP takes the return value from the sessionRead() handler, unserializes it
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
