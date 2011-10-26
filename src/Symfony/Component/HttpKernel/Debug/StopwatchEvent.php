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
    private $start;
    private $origin;
    private $category;
    private $started;

    /**
     * Constructor.
     *
     * @param integer $origin   The origin time in milliseconds
     * @param string  $category The event category
     */
    public function __construct($origin, $category = null)
    {
        $this->origin = $origin;
        $this->category = $category ?: 'default';
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
     * Starts a new event period.
     */
    public function start()
    {
        $this->started[] = $this->getNow();
    }

    /**
     * Stops the last started event period.
     */
    public function stop()
    {
        if (!count($this->started)) {
            throw new \LogicException('stop() called but start() has not been called before.');
        }

        $this->periods[] = array(array_pop($this->started), $this->getNow());
    }

    /**
     * Stops the current period and then starts a new one.
     */
    public function lap()
    {
        $this->stop();
        $this->start();
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
        return count($this->periods) ? $this->periods[count($this->periods) - 1][1] : 0;
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

        return sprintf('%.1f', $total);
    }

    private function getNow()
    {
        return sprintf('%.1f', microtime(true) * 1000 - $this->origin);
    }
}
