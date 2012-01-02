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

use Symfony\Component\HttpFoundation\AttributeBagInterface;
use Symfony\Component\HttpFoundation\FlashBagInterface;

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
class MockArraySessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var array
     */
    private $attributes = array();

    /**
     * @var array
     */
    private $flashes = array();

    /**
     * Injects array of attributes to simulate retrieval of existing session.
     *
     * @param array $array
     */
    public function setAttributes(array $array)
    {
        $this->attributes = $array;
    }

    /**
     * Injects array of flashes to simulate retrieval of existing session.
     *
     * @param array $array
     */
    public function setFlashes(array $array)
    {
        $this->flashes = $array;
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
        $this->attributeBag->initialize($this->attributes);
        $this->flashBag->initialize($this->flashes);
        $this->sessionId = $this->generateSessionId();
        session_id($this->sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false)
    {
        if ($this->options['auto_start'] && !$this->started) {
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
     * Generates a session ID.
     *
     * @return string
     */
    protected function generateSessionId()
    {
        return sha1(uniqid(mt_rand(), true));
    }
}
