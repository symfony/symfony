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

/**
 * Class TimeData
 * @package Symfony\Component\Profiler\ProfileData
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class TimeData implements ProfileDataInterface
{
    private $token;
    private $startTime;
    private $events;

    public function __construct($startTime, array $events)
    {
        $this->startTime = $startTime * 1000;
        $this->setEvents($events);
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
     * Gets the request events.
     *
     * @return array The request events
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Gets the request elapsed time.
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
     * Gets the initialization time.
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
     * Gets the request time.
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
    public function getName()
    {
        return 'time';
    }
}