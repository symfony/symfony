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
     * Constructor.
     *
     * @param int $start The relative time of the start of the period (in milliseconds)
     * @param int $end   The relative time of the end of the period (in milliseconds)
     */
    public function __construct($start, $end)
    {
        $this->start = (int) $start;
        $this->end = (int) $end;
        $this->memory = memory_get_usage(true);
    }

    /**
     * Gets the relative time of the start of the period.
     *
     * @return int     The time (in milliseconds)
     */
    public function getStartTime()
    {
        return $this->start;
    }

    /**
     * Gets the relative time of the end of the period.
     *
     * @return int     The time (in milliseconds)
     */
    public function getEndTime()
    {
        return $this->end;
    }

    /**
     * Gets the time spent in this period.
     *
     * @return int     The period duration (in milliseconds)
     */
    public function getDuration()
    {
        return $this->end - $this->start;
    }

    /**
     * Gets the memory usage.
     *
     * @return int     The memory usage (in bytes)
     */
    public function getMemory()
    {
        return $this->memory;
    }
}
