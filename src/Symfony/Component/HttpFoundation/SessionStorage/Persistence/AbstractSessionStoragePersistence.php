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
     * @param EventDispatcherInterface $dispatcher A concrete instance of a event dispatcher     *
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName)
    {
        $event = new SessionEvent();
        $this->dispatcher->dispatch(SessionPersistenceEvents::OPEN, $event);
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        $event = new SessionEvent();
        $this->dispatcher->dispatch(SessionPersistenceEvents::CLOSE, $event);
    }

    /**
     * {@inheritDoc}
     */
    public function write($id, $data)
    {
        $event = new SessionEvent($id);
        $this->dispatcher->dispatch(SessionPersistenceEvents::WRITE, $event);
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($id)
    {
        $event = new SessionEvent($id);
        $this->dispatcher->dispatch(SessionPersistenceEvents::DESTROY, $event);
    }

    /**
     * {@inheritDoc}
     */
    public function gc($maxlifetime)
    {
        $event = new SessionEvent();
        $this->dispatcher->dispatch(SessionPersistenceEvents::GC, $event);
    }

    /**
     * {@inheritDoc}
     */
    public function read($id)
    {
        $event = new SessionEvent($id);
        $this->dispatcher->dispatch(SessionPersistenceEvents::READ, $event);
    }
}
