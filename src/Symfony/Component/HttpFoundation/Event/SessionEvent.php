<?php

namespace Symfony\Component\HttpFoundation\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * SessionEvent
 *
 * @author Mark de Jong <mail@markdejong.org>
 */
class SessionEvent extends Event
{
    /**
     * @var string
     */
    private $sessionId;

    public function __construct($sessionId = null)
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
