<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Fixtures;

/**
 * The CPU time of the process, not the time the clock shows: a loaded machine, like a CI runner shared with other
 * jobs, slows the clock down many times, the work it does not.
 */
trait CpuTime
{
    /**
     * @return float The seconds the process ran so far, in user and system mode
     */
    private static function cpuNow(): float
    {
        $usage = getrusage();

        return $usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec'] + ($usage['ru_utime.tv_usec'] + $usage['ru_stime.tv_usec']) / 1e6;
    }
}
