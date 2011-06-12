<?php

namespace Symfony\Component\HttpFoundation\SessionStorage;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface;
use Symfony\Component\HttpFoundation\Event\SessionEvent;
use Symfony\Component\HttpFoundation\SessionEvents;

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
	 * Starts the session.
	 */
	function start()
	{
		$this->session->start();
		$event = new SessionEvent($this->getId());
		$this->dispatcher->dispatch(SessionEvents::START, $event);
	}

	/**
	 * Returns the session ID
	 *
	 * @return mixed  The session ID
	 *
	 * @throws \RuntimeException If the session was not started yet
	 */
	function getId()
	{
		return $this->session->getId();
	}

	/**
	 * Reads data from this storage.
	 *
	 * The preferred format for a key is directory style so naming conflicts can be avoided.
	 *
	 * @param  string $key  A unique key identifying your data
	 *
	 * @return mixed Data associated with the key
	 *
	 * @throws \RuntimeException If an error occurs while reading data from this storage
	 */
	function read($key)
	{
		$event = new SessionEvent($this->getId());
		$this->dispatcher->dispatch(SessionEvents::READ, $event);

		return $this->session->read($key);
	}

	/**
	 * Removes data from this storage.
	 *
	 * The preferred format for a key is directory style so naming conflicts can be avoided.
	 *
	 * @param  string $key  A unique key identifying your data
	 *
	 * @return mixed Data associated with the key
	 *
	 * @throws \RuntimeException If an error occurs while removing data from this storage
	 */
	function remove($key)
	{
		$event = new SessionEvent($this->getId());
		$this->dispatcher->dispatch(SessionEvents::REMOVE, $event);

		return $this->session->remove($key);
	}

	/**
	 * Writes data to this storage.
	 *
	 * The preferred format for a key is directory style so naming conflicts can be avoided.
	 *
	 * @param  string $key   A unique key identifying your data
	 * @param  mixed  $data  Data associated with your key
	 *
	 * @throws \RuntimeException If an error occurs while writing to this storage
	 */
	function write($key, $data)
	{
		$event = new SessionEvent($this->getId());
		$this->dispatcher->dispatch(SessionEvents::WRITE, $event);

		$this->session->write($key, $data);
	}

	/**
	 * Regenerates id that represents this storage.
	 *
	 * @param  Boolean $destroy Destroy session when regenerating?
	 *
	 * @return Boolean True if session regenerated, false if error
	 *
	 * @throws \RuntimeException If an error occurs while regenerating this storage
	 */
	function regenerate($destroy = false)
	{
		$this->dispatcher->dispatch(SessionEvents::PRE_REGENERATE, new SessionEvent($this->getId()));

		$success = $this->session->regenerate($destroy);

		if(true === $success)
		{
			$this->dispatcher->dispatch(SessionEvents::POST_REGENERATE, new SessionEvent($this->getId()));
		}

		return $success;
	}
}
