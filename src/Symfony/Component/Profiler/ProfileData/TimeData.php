<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\ProfileData;

use Symfony\Component\Stopwatch\Stopwatch;

/**
 * TimeData.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class TimeData implements ProfileDataInterface, TokenAwareProfileDataInterface
{
    private $startTime;
    private $events = array();
    private $stopwatch;

    public function __construct($startTime, Stopwatch $stopwatch = null)
    {
        $this->startTime = $startTime * 1000;
        $this->stopwatch = $stopwatch;
    }

    /**
     * Sets the request events.
     *
     * @param array $events The request events
     */
    protected function setEvents(array $events)
    {
        foreach ($events as $event) {
            $event->ensureStopped();
        }

        $this->events = $events;
    }

    /**
     * Returns the events.
     *
     * @return array The request events
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Returns the elapsed time.
     *
     * @return float The elapsed time
     */
    public function getDuration()
    {
        if (!isset($this->events['__section__'])) {
            return 0;
        }

        $lastEvent = $this->events['__section__'];

        return $lastEvent->getOrigin() + $lastEvent->getDuration() - $this->getStartTime();
    }

    /**
     * Returns the initialization time.
     *
     * This is the time spent until the beginning of the request handling.
     *
     * @return float The elapsed time
     */
    public function getInitTime()
    {
        if (!isset($this->events['__section__'])) {
            return 0;
        }

        return $this->events['__section__']->getOrigin() - $this->getStartTime();
    }

    /**
     * Returns the start time.
     *
     * @return int The time
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array('startTime' => $this->startTime, 'events' => $this->events));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->startTime = $data['startTime'];
        $this->events = $data['events'];
    }

    /**
     * @inheritDoc
     */
    public function setToken($token)
    {
        if (null !== $this->stopwatch) {
            $this->setEvents($this->stopwatch->getSectionEvents($token));
        }
    }

    public function getName()
    {
        return 'time';
    }
}
