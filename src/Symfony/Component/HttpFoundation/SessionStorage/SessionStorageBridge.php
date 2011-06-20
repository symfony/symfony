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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Event\SessionEvent;
use Symfony\Component\HttpFoundation\SessionEvents;

/**
 * SessionStorageBridge implements the SessionStorageInterface with a bridge pattern:
 * http://sourcemaking.com/design_patterns/bridge. It delegates all methods to the instance
 * from the constructor. It decorates the calls with event dispatching
 *
 * @author Mark de Jong <mail@markdejong.org>
 */
class SessionStorageBridge implements SessionStorageInterface
{
    /**
     * @var \Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface
     */
    private $session;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $dispatcher;

    function __construct(EventDispatcherInterface $dispatcher, SessionStorageInterface $session)
    {
        $this->dispatcher = $dispatcher;
        $this->session = $session;
    }

    /**
     * {@inheritDoc}
     */
    function start()
    {
        $this->session->start();
        $event = new SessionEvent($this->getId());
        $this->dispatcher->dispatch(SessionEvents::START, $event);
    }

    /**
     * {@inheritDoc}
     */
    function getId()
    {
        return $this->session->getId();
    }

    /**
     * {@inheritDoc}
     */
    function read($key)
    {
        $event = new SessionEvent($this->getId());
        $this->dispatcher->dispatch(SessionEvents::READ, $event);

        return $this->session->read($key);
    }

    /**
     * {@inheritDoc}
     */
    function remove($key)
    {
        $event = new SessionEvent($this->getId());
        $this->dispatcher->dispatch(SessionEvents::REMOVE, $event);

        return $this->session->remove($key);
    }

    /**
     * {@inheritDoc}
     */
    function write($key, $data)
    {
        $event = new SessionEvent($this->getId());
        $this->dispatcher->dispatch(SessionEvents::WRITE, $event);

        $this->session->write($key, $data);
    }

    /**
     * {@inheritDoc}
     */
    function regenerate($destroy = false)
    {
        $this->dispatcher->dispatch(SessionEvents::PRE_REGENERATE, new SessionEvent($this->getId()));

        $success = $this->session->regenerate($destroy);

        if (true === $success) {
            $this->dispatcher->dispatch(SessionEvents::POST_REGENERATE, new SessionEvent($this->getId()));
        }

        return $success;
    }
}
