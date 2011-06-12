<?php

namespace Symfony\Component\HttpFoundation\Event;

use Symfony\Component\EventDispatcher\Event;

class SessionEvent extends Event
{
	/**
	 * @var string
	 */
	private $sessionId;

	public function __construct($sessionId)
	{
		$this->sessionId = $sessionId;
	}

	/**
	 * @return string
	 */
	public function getSessionId()
	{
		return $this->sessionId;
	}
}
