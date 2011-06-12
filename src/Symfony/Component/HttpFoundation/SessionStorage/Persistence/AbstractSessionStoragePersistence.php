<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage\Persistence;

use Symfony\Component\HttpFoundation\Event\SessionEvent;
use Symfony\Component\HttpFoundation\SessionPersistenceEvents;
use Symfony\Component\HttpFoundation\SessionStorage\Persistence\SessionStoragePersistenceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * AbstractSessionStoragePersistence is a base class for creating session persistence classes
 * mainly used to dispatch events
 *
 * @author Mark de Jong <mail@markdejong.org>
 */
abstract class AbstractSessionStoragePersistence implements SessionStoragePersistenceInterface
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher A concrete instance of a event dispatcher     *
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Called upon opening a new session from (set by session_set_save_handler)
     *
     * @param string $savePath The path to save to
     * @param string $sessionName The name of the session
     * @return void
     */
    function open($savePath, $sessionName)
    {
        $event = new SessionEvent();
        $this->dispatcher->dispatch(SessionPersistenceEvents::OPEN, $event);
    }

    /**
     * Called upon closing a session from (set by session_set_save_handler)
     *
     * @return void
     */
    function close()
    {
        $event = new SessionEvent();
        $this->dispatcher->dispatch(SessionPersistenceEvents::CLOSE, $event);
    }

    /**
     * Called upon writing a session from (set by session_set_save_handler)
     *
     * @param string $id
     * @param string $data
     * @return void
     */
    function write($id, $data)
    {
        $event = new SessionEvent($id);
        $this->dispatcher->dispatch(SessionPersistenceEvents::WRITE, $event);
    }

    /**
     * Called upon destroying a session from (set by session_set_save_handler)
     *
     * @param  string $id
     * @return void
     */
    function destroy($id)
    {
        $event = new SessionEvent($id);
        $this->dispatcher->dispatch(SessionPersistenceEvents::DESTROY, $event);
    }

    /**
     * Called upon garbage collection (set by session_set_save_handler)
     *
     * @param int $maxlifetime
     * @return void
     */
    function gc($maxlifetime)
    {
        $event = new SessionEvent();
        $this->dispatcher->dispatch(SessionPersistenceEvents::GC, $event);
    }

    /**
     * Called upon reading a session from (set by session_set_save_handler)
     *
     * @param string $id
     * @return void
     */
    function read($id)
    {
        $event = new SessionEvent($id);
        $this->dispatcher->dispatch(SessionPersistenceEvents::READ, $event);
    }
}
