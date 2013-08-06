<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch;

/**
 * Represents an Period for an Event.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StopwatchPeriod
{
    private $start;
    private $end;
    private $memory;

    /**
     * Constructor
     *
     * @param integer $start The relative time of the start of the period
     * @param integer $end   The relative time of the end of the period
     */
    public function __construct($start, $end)
    {
        $this->start = (integer) $start;
        $this->end = (integer) $end;
        $this->memory = memory_get_usage(true);
    }

    /**
     * Gets the relative time of the start of the period.
     *
     * @return integer The time (in milliseconds)
     */
    public function getStartTime()
    {
        return $this->start;
    }

    /**
     * Gets the relative time of the end of the period.
     *
     * @return integer The time (in milliseconds)
     */
    public function getEndTime()
    {
        return $this->end;
    }

    /**
     * Gets the time spent in this period.
     *
     * @return integer The period duration (in milliseconds)
     */
    public function getDuration()
    {
        return $this->end - $this->start;
    }

    /**
     * Gets the memory usage.
     *
     * @return integer The memory usage (in bytes)
     */
    public function getMemory()
    {
        return $this->memory;
    }
}
