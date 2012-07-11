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

use Symfony\Component\HttpFoundation\Session\Storage\Handler\FileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;

/**
 * MockFileSessionStorage is used to mock sessions for
 * functional testing when done in a single PHP process.
 *
 * No PHP session is actually started since a session can be initialized
 * and shutdown only once per PHP execution cycle and this class does
 * not pollute any session related globals, including session_*() functions
 * or session.* PHP ini directives.
 *
 * @author Drak <drak@zikula.org>
 */
class MockFileSessionStorage extends MockArraySessionStorage
{
    /**
     * @var FileSessionHandler
     */
    private $handler;

    /**
     * Constructor.
     *
     * @param string             $savePath Path of directory to save session files.
     * @param string             $name     Session name.
     * @param FileSessionHandler $handler  Save handler
     * @param MetadataBag        $metaData Metadatabag
     */
    public function __construct($savePath = null, $name = 'MOCKSESSID', FileSessionHandler $handler = null, MetadataBag $metaData = null)
    {
        if (null == $handler) {
            $handler = new FileSessionHandler($savePath, 'mocksess_');
        }

        $this->handler = $handler;

        parent::__construct($name, $metaData);
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->started) {
            return true;
        }

        if (!$this->id) {
            $this->id = $this->generateId();
        }

        $this->read();

        $this->started = true;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false, $lifetime = null)
    {
        if (!$this->started) {
            $this->start();
        }

        if ($destroy) {
            $this->destroy();
        }

        return parent::regenerate($destroy, $lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->handler->write($this->id, serialize($this->data));

        // this is needed for Silex, where the session object is re-used across requests
        // in functional tests. In Symfony, the container is rebooted, so we don't have
        // this issue
        $this->started = false;
    }

    /**
     * Deletes a session from persistent storage.
     * Deliberately leaves session data in memory intact.
     */
    private function destroy()
    {
        $this->handler->destroy($this->id);
    }

    /**
     * Reads session from storage and loads session.
     */
    private function read()
    {
        $data = $this->handler->read($this->id);
        $this->data = $data ? unserialize($data) : array();

        $this->loadSession();
    }
}
