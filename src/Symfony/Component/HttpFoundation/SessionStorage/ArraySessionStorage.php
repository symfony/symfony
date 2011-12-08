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

/**
 * ArraySessionStorage mocks the session for unit tests.
 *
 * When doing functional testing, you should use FilesystemSessionStorage instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Drak <drak@zikula.org>
 */
class ArraySessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $sessionId;

    private $attributes = array();
    private $flashes = array();

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started) {
            return;
        }

        $this->flashBag->initialize($this->flashes);
        $this->attributesBag->initialize($this->attributes);
        $this->sessionId = $this->generateSessionId();
        session_id($this->sessionId);
        $this->started = true;
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false)
    {
        if (!$this->started) {
            $this->start();
        }

        if ($destroy) {
            $this->attributesBag->clear();
            $this->flashBag->clearAll();
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
}