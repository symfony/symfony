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
     * @param int|float $start         The relative time of the start of the period (in milliseconds)
     * @param int|float $end           The relative time of the end of the period (in milliseconds)
     * @param bool      $morePrecision If true, time is stored as float to keep the original microsecond precision
     */
    public function __construct($start, $end, bool $morePrecision = false)
    {
        $this->start = $morePrecision ? (float) $start : (int) $start;
        $this->end = $morePrecision ? (float) $end : (int) $end;
        $this->memory = memory_get_usage(true);
    }

    /**
     * Gets the relative time of the start of the period in milliseconds.
     *
     * @return int|float
     */
    public function getStartTime()
    {
        return $this->start;
    }

    /**
     * Gets the relative time of the end of the period in milliseconds.
     *
     * @return int|float
     */
    public function getEndTime()
    {
        return $this->end;
    }

    /**
     * Gets the time spent in this period in milliseconds.
     *
     * @return int|float
     */
    public function getDuration()
    {
        return $this->end - $this->start;
    }

    /**
     * Gets the memory usage in bytes.
     *
     * @return int
     */
    public function getMemory()
    {
        return $this->memory;
    }

    public function __toString(): string
    {
        return sprintf('%.2F MiB - %d ms', $this->getMemory() / 1024 / 1024, $this->getDuration());
    }
}
