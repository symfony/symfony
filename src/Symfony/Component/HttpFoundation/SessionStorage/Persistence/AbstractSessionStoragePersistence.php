<?php

namespace Symfony\Component\HttpFoundation\SessionStorage\Persistence;

use Symfony\Component\HttpFoundation\Event\SessionEvent;
use Symfony\Component\HttpFoundation\SessionPersistenceEvents;
use Symfony\Component\HttpFoundation\SessionStorage\Persistence\SessionStoragePersistenceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class AbstractSessionStoragePersistence implements SessionStoragePersistenceInterface
{
	/**
	 * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
	 */
	private $dispatcher;

	public function __construct(EventDispatcherInterface $dispatcher)
	{
		$this->dispatcher = $dispatcher;
	}

	function open($savePath, $sessionName)
	{
		$event = new SessionEvent();
		$this->dispatcher->dispatch(SessionPersistenceEvents::OPEN, $event);
	}

	function close()
	{
		$event = new SessionEvent();
		$this->dispatcher->dispatch(SessionPersistenceEvents::CLOSE, $event);
	}

	function write($id, $data)
	{
		$event = new SessionEvent($id);
		$this->dispatcher->dispatch(SessionPersistenceEvents::WRITE, $event);
	}

	function destroy($id)
	{
		$event = new SessionEvent($id);
		$this->dispatcher->dispatch(SessionPersistenceEvents::DESTROY, $event);
	}

	function gc($maxlifetime)
	{
		$event = new SessionEvent();
		$this->dispatcher->dispatch(SessionPersistenceEvents::GC, $event);
	}

	function read($id)
	{
		$event = new SessionEvent($id);
		$this->dispatcher->dispatch(SessionPersistenceEvents::READ, $event);
	}
}
