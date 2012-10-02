<?php

namespace Symfony\Component\Routing\Matcher;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Routing\Route;

class UrlMatcherEvent extends Event
{
    private $route;

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
     * @return Route
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
     * @param $status boolean|null
     */
    public function setStatus($status)
    {
        $this->status = $status;

        if ($this->status == self::REQUIREMENT_MISMATCH) {
            $this->stopPropagation();
        }
    }

    /**
     * Returns the set status
     *
     * @return boolean|null
     */
    public function getStatus()
    {
        return $this->status;
    }
}