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
 * @author Adamo Crespi <hello@aerendir.me>
 */
class StopwatchPeriod
{
    /** @var float|int $start */
    private $start;

    /** @var float|int $end */
    private $end;

    /** @var int $memory The amount of memory assigned to PHP */
    private $memory;

    /** @var int $memoryCurrent Of the memory assigned to PHP, the amount of memory currently consumed by the script */
    private $memoryCurrent;

    /** @var int $memoryPeak The max amount of memory assigned to PHP */
    private $memoryPeak;

    /** @var int $memoryPeakEmalloc The max amount of memory assigned to PHP and used by emalloc() */
    private $memoryPeakEmalloc;

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
        $this->memoryCurrent = memory_get_usage();
        $this->memoryPeak = memory_get_peak_usage(true);
        $this->memoryPeakEmalloc = memory_get_peak_usage();
    }

    /**
     * Gets the relative time of the start of the period.
     *
     * @return int|float The time (in milliseconds)
     */
    public function getStartTime()
    {
        return $this->start;
    }

    /**
     * Gets the relative time of the end of the period.
     *
     * @return int|float The time (in milliseconds)
     */
    public function getEndTime()
    {
        return $this->end;
    }

    /**
     * Gets the time spent in this period.
     *
     * @return int|float The period duration (in milliseconds)
     */
    public function getDuration()
    {
        return $this->end - $this->start;
    }

    /**
     * Gets the memory assigned to PHP.
     *
     * @return int The memory usage (in bytes)
     */
    public function getMemory()
    {
        return $this->memory;
    }

    /**
     * Of the memory assigned to PHP, gets the amount of memory currently used by the script.
     *
     * @return int
     */
    public function getMemoryCurrent(): int
    {
        return $this->memoryCurrent;
    }

    /**
     * Gets the max amount of memory assigned to PHP.
     *
     * @return int
     */
    public function getMemoryPeak(): int
    {
        return $this->memoryPeak;
    }

    /**
     * Gets the max amount of memory used by emalloc().
     *
     * @return int
     */
    public function getMemoryPeakEmalloc(): int
    {
        return $this->memoryPeakEmalloc;
    }
}
