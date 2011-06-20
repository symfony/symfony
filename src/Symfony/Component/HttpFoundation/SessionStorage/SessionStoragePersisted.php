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

use Symfony\Component\HttpFoundation\SessionStorage\Persistence\SessionStoragePersistenceInterface;

/**
 * SessionStoragePersisted is used to implement SessionStorage and bind
 * a concrete instance of SessionStoragePersistenceInterface to php's
 * session_set_save_handler
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Mark de Jong <mail@markdejong.org>
 */
class SessionStoragePersisted implements SessionStorageInterface
{
    static protected $sessionIdRegenerated = false;
    static protected $sessionStarted = false;

    /**
     * @var \Symfony\Component\HttpFoundation\SessionStorage\Persistence\SessionStoragePersistenceInterface
     */
    protected $persister;

    /**
     * @var array
     */
    protected $options;


    /**
     * Available options:
     *
     *  * name:     The cookie name (_SESS by default)
     *  * id:       The session id (null by default)
     *  * lifetime: Cookie lifetime
     *  * path:     Cookie path
     *  * domain:   Cookie domain
     *  * secure:   Cookie secure
     *  * httponly: Cookie http only
     *
     * The default values for most options are those returned by the session_get_cookie_params() function
     *
     * @param SessionStoragePersistenceInterface An concrete implementation of SessionStoragePersistenceInterface
     * @param array $options  An associative array of session options
     */
    public function __construct(SessionStoragePersistenceInterface $persister, array $options)
    {
        $this->persister = $persister;
        $this->options = $options;

        $cookieDefaults = session_get_cookie_params();

        $this->options = array_merge(array(
            'name' => '_SESS',
            'lifetime' => $cookieDefaults['lifetime'],
            'path' => $cookieDefaults['path'],
            'domain' => $cookieDefaults['domain'],
            'secure' => $cookieDefaults['secure'],
            'httponly' => isset($cookieDefaults['httponly']) ? $cookieDefaults['httponly'] : false,
         ), $options);

        session_name($this->options['name']);
    }

    /**
     * {@inheritDoc}
     */
    public function start()
    {
        if (self::$sessionStarted) {
            return;
        }

        // use this object as the session handler
        session_set_save_handler(
            array($this->persister, 'open'),
            array($this->persister, 'close'),
            array($this->persister, 'read'),
            array($this->persister, 'write'),
            array($this->persister, 'destroy'),
            array($this->persister, 'gc')
        );

        // disable native cache limiter as this is managed by HeaderBag directly
        session_cache_limiter(false);

        if (!ini_get('session.use_cookies') && isset($this->options['id']) && $this->options['id'] && $this->options['id'] != session_id()) {
            session_id($this->options['id']);
        }

        session_start();

        self::$sessionStarted = true;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        if (!self::$sessionStarted) {
            throw new \RuntimeException('The session must be started before reading its ID');
        }

        return session_id();
    }

    /**
     * {@inheritDoc}
     */
    public function read($key, $default = null)
    {
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function write($key, $data)
    {
        $_SESSION[$key] = $data;
    }

    /**
     * {@inheritDoc}
     */
    public function regenerate($destroy = false)
    {
        if (self::$sessionIdRegenerated) {
            return;
        }

        session_regenerate_id($destroy);

        self::$sessionIdRegenerated = true;
    }
}
