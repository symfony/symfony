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

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;

/**
 * MockArraySessionStorage mocks the session for unit tests.
 *
 * No PHP session is actually started since a session can be initialized
 * and shutdown only once per PHP execution cycle.
 *
 * When doing functional testing, you should use MockFileSessionStorage instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Drak <drak@zikula.org>
 */
class MockArraySessionStorage extends SessionStorage
{
    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var array
     */
    protected $sessionData = array();

    public function __construct(array $options = array())
    {
        parent::__construct($options, new NullSessionHandler());
    }

    /**
     * Sets the session data.
     *
     * @param array $array
     */
    public function setSessionData(array $array)
    {
        $this->sessionData = $array;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started && !$this->closed) {
            return true;
        }

        $this->started = true;
        $this->loadSession($this->sessionData);

        $this->sessionId = $this->generateSessionId();
        session_id($this->sessionId);

        return true;
    }


    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false)
    {
        if (!$this->started) {
            $this->start();
        }

        $this->sessionId = $this->generateSessionId();
        session_id($this->sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        if (!$this->started) {
            return '';
        }

        return $this->sessionId;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        // nothing to do since we don't persist the session data
        $this->closed = false;
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
        $this->sessionData = array();

        // reconnect the bags to the session
        $this->loadSession($this->sessionData);
    }

    /**
     * {@inheritdoc}
     */
    public function getBag($name)
    {
        if (!isset($this->bags[$name])) {
            throw new \InvalidArgumentException(sprintf('The SessionBagInterface %s is not registered.', $name));
        }

        if (!$this->started) {
            $this->start();
        }

        return $this->bags[$name];
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
