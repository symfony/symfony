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
use Symfony\Component\HttpFoundation\AttributesBag;
use Symfony\Component\HttpFoundation\AttributesBagInterface;

/**
 * This provides a base class for session attribute storage.
 *
 * @author Drak <drak@zikula.org>
 */
abstract class AbstractSessionStorage implements SessionStorageInterface
{
    /**
     * @var \Symfony\Component\HttpFoundation\FlashBagInterface
     *
     * @api
     */
    protected $flashBag;

    /**
     * @var \Symfony\Component\HttpFoundation\AttributesBagInterface
     *
     * @api
     */
    protected $attributesBag;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var boolean
     *
     * @api
     */
    protected $started = false;

    /**
     * Constructor.
     *
     * Depending on how you want the storage driver to behave you probably
     * want top override this constructor entirely.
     *
     * List of options for $options array with their defaults from
     * See session.* for values at http://www.php.net/manual/en/ini.list.php
     * But we omit 'session.` from the beginning of the keys.
     *
     * auto_start, "0"
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
     *
     * @param AttributesBagInterface $attributesBag An instance of AttributesBagIntrface.
     * @param FlashBagInterface      $flashBag      An instance of FlashBagInterface.
     * @param array                  $options       Session options.
     */
    public function __construct(AttributesBagInterface $attributesBag, FlashBagInterface $flashBag, array $options = array())
    {
        $this->attributesBag = $attributesBag;
        $this->flashBag = $flashBag;
        $this->setOptions($options);
        $this->registerSaveHandlers();
        $this->registerShutdownFunction();
    }

    /**
     * {@inheritdoc}
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
    public function getAttributesBag()
    {
        if (!$this->started) {
            $this->start();
        }

        return $this->attributesBag;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started) {
            // Nothing to do as the session is already started.
            return;
        }

        // disable native cache limiter as this is managed by HeaderBag directly
        session_cache_limiter(false);

        // generate random session ID
        if (!session_id()) {
            session_id($this->generateSessionId());
        }

        // start the session
        if (!session_start()) {
            throw new \RuntimeException('Failed to start the session');
        }

        // after starting the session, PHP retrieves the session from whatever handlers were set
        // either PHP's internal, or the ones we set using sssion_set_save_handler().  PHP takes
        // the return value from the sessionRead() handler and populates $_SESSION with it automatically.
        $key = $this->attributesBag->getStorageKey();
        $_SESSION[$key] = isset($_SESSION[$key]) ? $_SESSION[$key] : array();
        $this->attributesBag->initialize($_SESSION[$key]);

        $key = $this->flashBag->getStorageKey();
        $_SESSION[$key] = isset($_SESSION[$key]) ? $_SESSION[$key] : array();
        $this->flashBag->initialize($_SESSION[$key]);

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
     * Note we omit 'session.' from the beginning of the keys.
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
     * NOTE:
     *
     * To use PHP native save handlers, override this method using ini_set with
     * session.save_handlers and session.save_path e.g.
     *
     *     ini_set('session.save_handlers', 'files');
     *     ini_set('session.save_path', /tmp');
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
     * This method is required to avoid strange issues when using PHP objects as
     * session save handlers.
     */
    protected function registerShutdownFunction()
    {
        register_shutdown_function('session_write_close');
    }

    /**
     * Generates a session ID.
     *
     * @return string
     */
    protected function generateSessionId()
    {
        return sha1(uniqid(mt_rand(), true));
    }
}
