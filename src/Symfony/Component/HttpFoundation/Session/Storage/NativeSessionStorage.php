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
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

/**
 * This provides a base class for session attribute storage.
 *
 * @author Drak <drak@zikula.org>
 */
class NativeSessionStorage implements SessionStorageInterface
{
    /**
     * @var SessionBagInterface[]
     */
    protected $bags = array();

    /**
     * @var bool
     */
    protected $started = false;

    /**
     * @var bool
     */
    protected $closed = false;

    /**
     * @var AbstractProxy|\SessionHandlerInterface
     */
    protected $saveHandler;

    /**
     * @var MetadataBag
     */
    protected $metadataBag;

    /**
     * Depending on how you want the storage driver to behave you probably
     * want to override this constructor entirely.
     *
     * List of options for $options array with their defaults.
     *
     * @see http://php.net/session.configuration for options
     * but we omit 'session.' from the beginning of the keys for convenience.
     *
     * ("auto_start", is not supported as it tells PHP to start a session before
     * PHP starts to execute user-land code. Setting during runtime has no effect).
     *
     * cache_limiter, "" (use "0" to prevent headers from being sent entirely).
     * cache_expire, "0"
     * cookie_domain, ""
     * cookie_httponly, ""
     * cookie_lifetime, "0"
     * cookie_path, "/"
     * cookie_secure, ""
     * gc_divisor, "100"
     * gc_maxlifetime, "1440"
     * gc_probability, "1"
     * lazy_write, "1"
     * name, "PHPSESSID"
     * referer_check, ""
     * serialize_handler, "php"
     * use_strict_mode, "0"
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
     * sid_length, "32"
     * sid_bits_per_character, "5"
     * trans_sid_hosts, $_SERVER['HTTP_HOST']
     * trans_sid_tags, "a=href,area=href,frame=src,form="
     *
     * @param array                         $options Session configuration options
     * @param \SessionHandlerInterface|null $handler
     * @param MetadataBag                   $metaBag MetadataBag
     */
    public function __construct(array $options = array(), $handler = null, MetadataBag $metaBag = null)
    {
        $options += array(
            'cache_limiter' => '',
            'cache_expire' => 0,
            'use_cookies' => 1,
            'lazy_write' => 1,
            'use_strict_mode' => 1,
        );

        session_register_shutdown();

        $this->setMetadataBag($metaBag);
        $this->setOptions($options);
        $this->setSaveHandler($handler);
    }

    /**
     * Gets the save handler instance.
     *
     * @return AbstractProxy|\SessionHandlerInterface
     */
    public function getSaveHandler()
    {
        return $this->saveHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started) {
            return true;
        }

        if (\PHP_SESSION_ACTIVE === session_status()) {
            throw new \RuntimeException('Failed to start the session: already started by PHP.');
        }

        if (ini_get('session.use_cookies') && headers_sent($file, $line)) {
            throw new \RuntimeException(sprintf('Failed to start the session because headers have already been sent by "%s" at line %d.', $file, $line));
        }

        // ok to try and start the session
        if (!session_start()) {
            throw new \RuntimeException('Failed to start the session');
        }

        $this->loadSession();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->saveHandler->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->saveHandler->setId($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->saveHandler->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        $this->saveHandler->setName($name);
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false, $lifetime = null)
    {
        // Cannot regenerate the session ID for non-active sessions.
        if (\PHP_SESSION_ACTIVE !== session_status()) {
            return false;
        }

        if (headers_sent()) {
            return false;
        }

        if (null !== $lifetime) {
            ini_set('session.cookie_lifetime', $lifetime);
        }

        if ($destroy) {
            $this->metadataBag->stampNew();
        }

        $isRegenerated = session_regenerate_id($destroy);

        // The reference to $_SESSION in session bags is lost in PHP7 and we need to re-create it.
        // @see https://bugs.php.net/bug.php?id=70013
        $this->loadSession();

        return $isRegenerated;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $session = $_SESSION;

        foreach ($this->bags as $bag) {
            if (empty($_SESSION[$key = $bag->getStorageKey()])) {
                unset($_SESSION[$key]);
            }
        }
        if (array($key = $this->metadataBag->getStorageKey()) === array_keys($_SESSION)) {
            unset($_SESSION[$key]);
        }

        // Register custom error handler to catch a possible failure warning during session write
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, $errno, E_WARNING, $errfile, $errline);
        }, E_WARNING);

        try {
            $e = null;
            session_write_close();
        } catch (\ErrorException $e) {
        } finally {
            restore_error_handler();
            $_SESSION = $session;
        }
        if (null !== $e) {
            // The default PHP error message is not very helpful, as it does not give any information on the current save handler.
            // Therefore, we catch this error and trigger a warning with a better error message
            $handler = $this->getSaveHandler();
            if ($handler instanceof SessionHandlerProxy) {
                $handler = $handler->getHandler();
            }

            trigger_error(sprintf('session_write_close(): Failed to write session data with %s handler', get_class($handler)), E_USER_WARNING);
        }

        $this->closed = true;
        $this->started = false;
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
        if ($this->started) {
            throw new \LogicException('Cannot register a bag when the session is already started.');
        }

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

        if (!$this->started && $this->saveHandler->isActive()) {
            $this->loadSession();
        } elseif (!$this->started) {
            $this->start();
        }

        return $this->bags[$name];
    }

    public function setMetadataBag(MetadataBag $metaBag = null)
    {
        if (null === $metaBag) {
            $metaBag = new MetadataBag();
        }

        $this->metadataBag = $metaBag;
    }

    /**
     * Gets the MetadataBag.
     *
     * @return MetadataBag
     */
    public function getMetadataBag()
    {
        return $this->metadataBag;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Sets session.* ini variables.
     *
     * For convenience we omit 'session.' from the beginning of the keys.
     * Explicitly ignores other ini keys.
     *
     * @param array $options Session ini directives array(key => value)
     *
     * @see http://php.net/session.configuration
     */
    public function setOptions(array $options)
    {
        if (headers_sent() || \PHP_SESSION_ACTIVE === session_status()) {
            return;
        }

        $validOptions = array_flip(array(
            'cache_expire', 'cache_limiter', 'cookie_domain', 'cookie_httponly',
            'cookie_lifetime', 'cookie_path', 'cookie_secure',
            'gc_divisor', 'gc_maxlifetime', 'gc_probability',
            'lazy_write', 'name', 'referer_check',
            'serialize_handler', 'use_strict_mode', 'use_cookies',
            'use_only_cookies', 'use_trans_sid', 'upload_progress.enabled',
            'upload_progress.cleanup', 'upload_progress.prefix', 'upload_progress.name',
            'upload_progress.freq', 'upload_progress.min_freq', 'url_rewriter.tags',
            'sid_length', 'sid_bits_per_character', 'trans_sid_hosts', 'trans_sid_tags',
        ));

        foreach ($options as $key => $value) {
            if (isset($validOptions[$key])) {
                ini_set('url_rewriter.tags' !== $key ? 'session.'.$key : $key, $value);
            }
        }
    }

    /**
     * Registers session save handler as a PHP session handler.
     *
     * To use internal PHP session save handlers, override this method using ini_set with
     * session.save_handler and session.save_path e.g.
     *
     *     ini_set('session.save_handler', 'files');
     *     ini_set('session.save_path', '/tmp');
     *
     * or pass in a \SessionHandler instance which configures session.save_handler in the
     * constructor, for a template see NativeFileSessionHandler or use handlers in
     * composer package drak/native-session
     *
     * @see http://php.net/session-set-save-handler
     * @see http://php.net/sessionhandlerinterface
     * @see http://php.net/sessionhandler
     * @see http://github.com/drak/NativeSession
     *
     * @param \SessionHandlerInterface|null $saveHandler
     *
     * @throws \InvalidArgumentException
     */
    public function setSaveHandler($saveHandler = null)
    {
        if (!$saveHandler instanceof AbstractProxy &&
            !$saveHandler instanceof \SessionHandlerInterface &&
            null !== $saveHandler) {
            throw new \InvalidArgumentException('Must be instance of AbstractProxy; implement \SessionHandlerInterface; or be null.');
        }

        // Wrap $saveHandler in proxy and prevent double wrapping of proxy
        if (!$saveHandler instanceof AbstractProxy && $saveHandler instanceof \SessionHandlerInterface) {
            $saveHandler = new SessionHandlerProxy($saveHandler);
        } elseif (!$saveHandler instanceof AbstractProxy) {
            $saveHandler = new SessionHandlerProxy(new StrictSessionHandler(new \SessionHandler()));
        }
        $this->saveHandler = $saveHandler;

        if (headers_sent() || \PHP_SESSION_ACTIVE === session_status()) {
            return;
        }

        if ($this->saveHandler instanceof SessionHandlerProxy) {
            session_set_save_handler($this->saveHandler->getHandler(), false);
        } elseif ($this->saveHandler instanceof \SessionHandlerInterface) {
            session_set_save_handler($this->saveHandler, false);
        }
    }

    /**
     * Load the session with attributes.
     *
     * After starting the session, PHP retrieves the session from whatever handlers
     * are set to (either PHP's internal, or a custom save handler set with session_set_save_handler()).
     * PHP takes the return value from the read() handler, unserializes it
     * and populates $_SESSION with the result automatically.
     */
    protected function loadSession(array &$session = null)
    {
        if (null === $session) {
            $session = &$_SESSION;
        }

        $bags = array_merge($this->bags, array($this->metadataBag));

        foreach ($bags as $bag) {
            $key = $bag->getStorageKey();
            $session[$key] = isset($session[$key]) ? $session[$key] : array();
            $bag->initialize($session[$key]);
        }

        $this->started = true;
        $this->closed = false;
    }
}
