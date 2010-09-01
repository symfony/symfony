<?php

namespace Symfony\Component\HttpFoundation\SessionStorage;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * NativeSessionStorage.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class NativeSessionStorage implements SessionStorageInterface
{
    static protected $sessionIdRegenerated = false;
    static protected $sessionStarted       = false;

    protected $options;

    /**
     * Available options:
     *
     *  * session_name:            The cookie name (symfony by default)
     *  * session_id:              The session id (null by default)
     *  * session_cookie_lifetime: Cookie lifetime
     *  * session_cookie_path:     Cookie path
     *  * session_cookie_domain:   Cookie domain
     *  * session_cookie_secure:   Cookie secure
     *  * session_cookie_httponly: Cookie http only
     *
     * The default values for all 'session_cookie_*' options are those returned by the session_get_cookie_params() function
     *
     * @param array $options  An associative array of options
     *
     */
    public function __construct(array $options = array())
    {
        $cookieDefaults = session_get_cookie_params();

        $this->options = array_merge(array(
            'session_name'            => 'SYMFONY_SESSION',
            'session_cookie_lifetime' => $cookieDefaults['lifetime'],
            'session_cookie_path'     => $cookieDefaults['path'],
            'session_cookie_domain'   => $cookieDefaults['domain'],
            'session_cookie_secure'   => $cookieDefaults['secure'],
            'session_cookie_httponly' => isset($cookieDefaults['httponly']) ? $cookieDefaults['httponly'] : false,
            'session_cache_limiter'   => 'none',
        ), $options);

        session_name($this->options['session_name']);
    }

    /**
     * Starts the session.
     */
    public function start()
    {
        if (self::$sessionStarted) {
            return;
        }

        session_set_cookie_params(
            $this->options['session_cookie_lifetime'],
            $this->options['session_cookie_path'],
            $this->options['session_cookie_domain'],
            $this->options['session_cookie_secure'],
            $this->options['session_cookie_httponly']
        );

        if (null !== $this->options['session_cache_limiter']) {
            session_cache_limiter($this->options['session_cache_limiter']);
        }

        if (!ini_get('session.use_cookies') && $this->options['session_id'] && $this->options['session_id'] != session_id()) {
            session_id($this->options['session_id']);
        }

        session_start();

        self::$sessionStarted = true;
    }

    /**
     * Reads data from this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param string $key A unique key identifying your data
     *
     * @return mixed Data associated with the key
     */
    public function read($key, $default = null)
    {
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    /**
     * Removes data from this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param  string $key  A unique key identifying your data
     *
     * @return mixed Data associated with the key
     */
    public function remove($key)
    {
        $retval = null;

        if (isset($_SESSION[$key])) {
            $retval = $_SESSION[$key];
            unset($_SESSION[$key]);
        }

        return $retval;
    }

    /**
     * Writes data to this storage.
     *
     * The preferred format for a key is directory style so naming conflicts can be avoided.
     *
     * @param string $key   A unique key identifying your data
     * @param mixed  $data  Data associated with your key
     *
     */
    public function write($key, $data)
    {
        $_SESSION[$key] = $data;
    }

    /**
     * Regenerates id that represents this storage.
     *
     * @param  boolean $destroy Destroy session when regenerating?
     *
     * @return boolean True if session regenerated, false if error
     *
     */
    public function regenerate($destroy = false)
    {
        if (self::$sessionIdRegenerated) {
            return;
        }

        // regenerate a new session id once per object
        session_regenerate_id($destroy);

        self::$sessionIdRegenerated = true;
    }
}
