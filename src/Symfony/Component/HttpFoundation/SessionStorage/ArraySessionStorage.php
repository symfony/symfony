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
    private $sessionId;
    
    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->attributes = array();
        
        $flashes = array();
        $this->flashBag->initialize($flashes);
        $this->started = true;
        $this->sessionId = session_id($this->generateSessionId());
    }
    
    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false)
    {
        if ($destroy) {
            $this->clear();
            $this->flashBag->clearAll();
        }
        
        $this->sessionId = session_id($this->generateSessionId());

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