<?php

namespace Symfony\Component\Routing\Matcher;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Routing\Route;

class UrlMatcherEvent extends Event
{
    /** @var \Symfony\Component\Routing\Route */
    private $route;

    /** @var boolean */
    private $status;

    const REQUIREMENT_MISMATCH = 2;
    const REQUIREMENT_MATCH = 1;

    public function __construct(Route $route)
    {
        $this->route = $route;
    }

    /**
     * Returns the route being matched
     *
     * @return \Symfony\Component\Routing\Route
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Set the result of the requirement match
     *
     * 0 - Mismatch
     * 1 - Match
     * null - No vote
     *
     * First listener to set to Mismatch wins
     *
     * @param $result boolean
     */
    public function setStatus($result)
    {
        $this->status = $result;

        if ($this->status == self::REQUIREMENT_MISMATCH) {
            $this->stopPropagation();
        }
    }

    public function getStatus()
    {
        return $this->status;
    }
}