<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\HttpKernel\Debug;

/**
 * Represents an Event managed by Stopwatch.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StopwatchEvent
{
    private $periods;
    private $origin;
    private $category;
    private $started;

    /**
     * Constructor.
     *
     * @param float   $origin   The origin time in milliseconds
     * @param string  $category The event category
     *
     * @throws \InvalidArgumentException When the raw time is not valid
     */
    public function __construct($origin, $category = null)
    {
        $this->origin = $this->formatTime($origin);
        $this->category = is_string($category) ? $category : 'default';
        $this->started = array();
        $this->periods = array();
    }

    /**
     * Gets the category.
     *
     * @return string The category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Gets the origin.
     *
     * @return integer The origin in milliseconds
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * Updates the origin.
     *
     * @param float $origin The origin time in milliseconds
     *
     * @return StopwatchEvent The event
     *
     * @throws \InvalidArgumentException When the raw time is not valid
     */
    public function setOrigin($origin)
    {
        $origin = $this->formatTime($origin);
        $delta = $this->origin - $origin;
        $this->origin = $origin;
        foreach ($this->started as $i => $time) {
            $this->started[$i] = $this->formatTime($time + $delta);
        }
        foreach ($this->periods as $i => $period) {
            $this->periods[$i] = array(
                $this->formatTime($period[0] + $delta),
                $this->formatTime($period[1] + $delta)
            );
        }

        return $this;
    }

    /**
     * Merges two events.
     *
     * @param StopWatchEvent $event The event to merge
     *
     * @return StopwatchEvent The event
     */
    public function merge(StopWatchEvent $event)
    {
        $this->periods = array_merge($this->periods, $event->setOrigin($this->origin)->getPeriods());

        return $this;
    }

    /**
     * Starts a new event period.
     *
     * @return StopwatchEvent The event
     */
    public function start()
    {
        $this->started[] = $this->getNow();

        return $this;
    }

    /**
     * Stops the last started event period.
     *
     * @return StopwatchEvent The event
     */
    public function stop()
    {
        if (!count($this->started)) {
            throw new \LogicException('stop() called but start() has not been called before.');
        }

        $this->periods[] = array(array_pop($this->started), $this->getNow());

        return $this;
    }

    /**
     * Stops the current period and then starts a new one.
     *
     * @return StopwatchEvent The event
     */
    public function lap()
    {
        return $this->stop()->start();
    }

    /**
     * Stops all non already stopped periods.
     */
    public function ensureStopped()
    {
        while (count($this->started)) {
            $this->stop();
        }
    }

    /**
     * Gets all event periods.
     *
     * @return array An array of periods
     */
    public function getPeriods()
    {
        return $this->periods;
    }

    /**
     * Gets the relative time of the start of the first period.
     *
     * @return integer The time (in milliseconds)
     */
    public function getStartTime()
    {
        return isset($this->periods[0]) ? $this->periods[0][0] : 0;
    }

    /**
     * Gets the relative time of the end of the last period.
     *
     * @return integer The time (in milliseconds)
     */
    public function getEndTime()
    {
        return ($count = count($this->periods)) ? $this->periods[$count - 1][1] : 0;
    }

    /**
     * Gets the total time of all periods.
     *
     * @return integer The time (in milliseconds)
     */
    public function getTotalTime()
    {
        $total = 0;
        foreach ($this->periods as $period) {
            $total += $period[1] - $period[0];
        }

        return $this->formatTime($total);
    }

    /**
     * Return the current time relative to origin.
     *
     * @return float Time in ms
     */
    protected function getNow()
    {
        return $this->formatTime(microtime(true) * 1000 - $this->origin);
    }

    /**
     * Formats a time.
     *
     * @param numerical $time A raw time
     *
     * @return float The formatted time
     *
     * @throws \InvalidArgumentException When the raw time is not valid
     */
    private function formatTime($time)
    {
        if (!is_numeric($time)) {
            throw new \InvalidArgumentException('The time must be a numerical value');
        }

        return round($time, 1);
    }
}
